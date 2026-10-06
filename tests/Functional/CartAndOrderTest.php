<?php

namespace App\Tests\Functional;

use App\Entity\Order;
use App\Service\CartService;
use App\Service\OrderService;
use App\Tests\Support\FakeGateway;
use App\Tests\Support\ShopWebTestCase;

class CartAndOrderTest extends ShopWebTestCase
{
    public function testAjoutAuPanierExigeLeJetonDuFormulaire(): void
    {
        $product = $this->createProduct();
        $this->client->loginUser($this->createUser());

        $this->client->request('POST', '/panier/ajouter/' . $product->getId(), ['quantity' => 1]);
        self::assertResponseStatusCodeSame(403);

        $this->client->request('POST', '/panier/ajouter/' . $product->getId(), ['quantity' => 1, '_token' => 'faux']);
        self::assertResponseStatusCodeSame(403);
    }

    /**
     * Régression : une quantité négative était acceptée. Elle faisait baisser le total du panier
     * et augmentait le stock à la commande.
     */
    public function testQuantiteNegativeOuExcessiveRefusee(): void
    {
        $product = $this->createProduct('79.99', 5);
        $this->client->loginUser($this->createUser());

        $crawler = $this->client->request('GET', '/produits/' . $product->getSlug());
        $token = $crawler->filter('form[data-cart-add] input[name="_token"]')->attr('value');
        $post = fn (int $quantity) => $this->client->request('POST', '/panier/ajouter/' . $product->getId(), ['quantity' => $quantity, '_token' => $token], [], ['HTTP_X_REQUESTED_WITH' => 'fetch']);

        foreach ([-3, 0, 6, CartService::MAX_QUANTITY + 1] as $quantity) {
            $post($quantity);
            self::assertResponseStatusCodeSame(422, 'quantité ' . $quantity);
            self::assertFalse(json_decode((string) $this->client->getResponse()->getContent(), true)['ok']);
        }

        self::assertSame(0, (int) $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM cart_item'));

        $post(2);
        self::assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertTrue($data['ok']);
        self::assertSame(2, $data['count']);
        self::assertSame("159,98\u{00A0}€", $data['total']);

        // 2 déjà au panier + 4 dépasserait le stock de 5.
        $post(4);
        self::assertResponseStatusCodeSame(422);
    }

    public function testCommandeTotalExactStockReserveEtPaiementLance(): void
    {
        $product = $this->createProduct('79.99', 10);
        $this->client->loginUser($this->createUser());

        $this->addToCart($product, 3);
        $number = $this->placeOrder();

        // La commande enchaîne sur la page de paiement du prestataire.
        self::assertResponseRedirects('https://checkout.stripe.com/c/pay/' . FakeGateway::lastSessionId());

        $order = $this->em()->getRepository(Order::class)->findOneBy(['orderNumber' => $number]);
        self::assertMatchesRegularExpression('/^BX-[A-HJ-NP-Z2-9]{10}$/', $number);
        self::assertSame('239.97', $order->getTotal(), '3 × 79,99 € = 239,97 €');
        self::assertSame(23997, FakeGateway::$sessions[FakeGateway::lastSessionId()]->amountTotal);
        self::assertSame(7, $this->stockOf($product));
        self::assertSame(0, (int) $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM cart_item'));
        self::assertTrue($order->isAwaitingPayment());
    }

    /** Le prix payé est celui du catalogue au moment de la commande, pas celui mémorisé dans le panier. */
    public function testPrixDuCatalogueAuMomentDeLaCommande(): void
    {
        $product = $this->createProduct('50.00', 10);
        $this->client->loginUser($this->createUser());
        $this->addToCart($product, 2);

        $this->em()->getConnection()->executeStatement('UPDATE product SET price = 65.50 WHERE id = ?', [$product->getId()]);
        $this->em()->clear();

        $crawler = $this->client->request('GET', '/panier/');
        self::assertStringContainsString('Le prix de', $crawler->filter('.flash--warning')->text());
        self::assertStringContainsString('131,00', $crawler->filter('.totals__grand dd')->text());

        $number = $this->placeOrder();
        self::assertSame('131.00', $this->em()->getConnection()->fetchOne('SELECT total FROM "order" WHERE order_number = ?', [$number]));
    }

    /** Deux clients ne peuvent pas acheter le même dernier exemplaire. */
    public function testPasDeSurvente(): void
    {
        $product = $this->createProduct('20.00', 1);
        $first = $this->createUser('premier@exemple.test');
        $second = $this->createUser('second@exemple.test');

        // Chacun met le dernier exemplaire dans son panier.
        $this->client->loginUser($first);
        $this->addToCart($product, 1);
        $this->client->loginUser($second);
        $this->addToCart($product, 1);
        self::assertSame(2, (int) $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM cart_item'));

        $this->client->loginUser($first);
        $this->placeOrder();
        self::assertSame(0, $this->stockOf($product));

        $this->client->loginUser($second);
        $this->client->request('GET', '/commande/creer');
        self::assertResponseRedirects('/panier/', null, 'Le second client est renvoyé à son panier.');
        self::assertSame(1, (int) $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM "order"'));
        self::assertSame(0, $this->stockOf($product), 'Le stock ne devient jamais négatif.');
    }

    public function testUneCommandeNEstVisibleQueParSonClient(): void
    {
        $product = $this->createProduct();
        $owner = $this->createUser('proprietaire@exemple.test');
        $this->client->loginUser($owner);
        $this->addToCart($product);
        $number = $this->placeOrder();
        $id = (int) $this->em()->getConnection()->fetchOne('SELECT id FROM "order" WHERE order_number = ?', [$number]);

        $this->client->request('GET', '/commande/' . $id);
        self::assertResponseIsSuccessful();

        $this->client->loginUser($this->createUser('curieux@exemple.test'));
        foreach (['/commande/' . $id, '/commande/confirmation/' . $number] as $path) {
            $this->client->request('GET', $path);
            self::assertResponseStatusCodeSame(404, $path);
        }
        $this->client->request('POST', '/paiement/commande/' . $number);
        self::assertResponseStatusCodeSame(404);
        $this->client->request('POST', '/commande/' . $id . '/annuler');
        self::assertResponseStatusCodeSame(404);
    }

    public function testAnnulationParLeClientRemetLeStock(): void
    {
        $product = $this->createProduct('30.00', 4);
        $this->client->loginUser($this->createUser());
        $this->addToCart($product, 3);
        $number = $this->placeOrder();
        self::assertSame(1, $this->stockOf($product));

        $id = (int) $this->em()->getConnection()->fetchOne('SELECT id FROM "order" WHERE order_number = ?', [$number]);
        $crawler = $this->client->request('GET', '/commande/' . $id);
        $this->client->submit($crawler->filter('form[action$="/annuler"]')->form());
        self::assertResponseRedirects('/commande/' . $id);

        self::assertSame(4, $this->stockOf($product));
        self::assertSame('cancelled', $this->em()->getConnection()->fetchOne('SELECT status FROM "order" WHERE id = ?', [$id]));
    }

    /** Une commande jamais payée ne bloque pas le stock indéfiniment. */
    public function testCommandeNonPayeeExpireEtLibereLeStock(): void
    {
        $product = $this->createProduct('30.00', 4);
        $this->client->loginUser($this->createUser());
        $this->addToCart($product, 4);
        $this->placeOrder();
        self::assertSame(0, $this->stockOf($product));

        $service = static::getContainer()->get(OrderService::class);
        self::assertSame(0, $service->expireUnpaidOrders(), 'Une commande récente est conservée.');
        self::assertSame(1, $service->expireUnpaidOrders(new \DateTimeImmutable('+3 hours')));
        self::assertSame(4, $this->stockOf($product));
    }
}
