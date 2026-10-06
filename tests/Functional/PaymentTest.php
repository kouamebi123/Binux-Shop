<?php

namespace App\Tests\Functional;

use App\Entity\Order;
use App\Entity\Product;
use App\Service\OrderService;
use App\Tests\Support\FakeGateway;
use App\Tests\Support\ShopWebTestCase;

class PaymentTest extends ShopWebTestCase
{
    private Product $product;
    private string $number;
    private int $orderId;

    private function orderOf(string $price = '79.99', int $quantity = 2): void
    {
        $this->product = $this->createProduct($price, 10);
        $this->client->loginUser($this->createUser());
        $this->addToCart($this->product, $quantity);
        $this->number = $this->placeOrder();
        $this->orderId = (int) $this->em()->getConnection()->fetchOne('SELECT id FROM "order" WHERE order_number = ?', [$this->number]);
    }

    private function status(): array
    {
        return $this->em()->getConnection()->fetchAssociative(
            'SELECT o.status AS order_status, p.status AS payment_status, p.paid_at FROM "order" o LEFT JOIN payment p ON p.order_ref_id = o.id WHERE o.id = ?',
            [$this->orderId]
        );
    }

    public function testPaiementConfirmeLaCommande(): void
    {
        $this->orderOf();
        $session = FakeGateway::lastSessionId();

        // Tant que Stripe ne dit pas « payé », rien ne change.
        $this->client->request('GET', '/paiement/succes?session_id=' . $session);
        self::assertResponseRedirects('/commande/mes-commandes');
        self::assertSame('pending', $this->status()['order_status']);

        FakeGateway::pay($session);
        $this->client->request('GET', '/paiement/succes?session_id=' . $session);
        self::assertResponseRedirects('/commande/confirmation/' . $this->number);

        $status = $this->status();
        self::assertSame('processing', $status['order_status']);
        self::assertSame('succeeded', $status['payment_status']);
        self::assertNotNull($status['paid_at']);

        // Rejouer l'adresse de retour ne change rien.
        $this->client->request('GET', '/paiement/succes?session_id=' . $session);
        self::assertResponseRedirects('/commande/confirmation/' . $this->number);
        self::assertSame(1, (int) $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM payment'));
    }

    /** Un montant encaissé différent du total de la commande ne la confirme jamais. */
    public function testMontantIncoherentNonConfirme(): void
    {
        $this->orderOf('79.99', 2);
        $session = FakeGateway::lastSessionId();

        FakeGateway::pay($session, 15996); // un centime par article en moins : l'ancien défaut d'arrondi
        $this->client->request('GET', '/paiement/succes?session_id=' . $session);
        self::assertResponseRedirects('/commande/mes-commandes');
        self::assertSame('pending', $this->status()['order_status']);
        self::assertSame('pending', $this->status()['payment_status']);

        FakeGateway::pay($session, 15998, 'usd');
        $this->client->request('GET', '/paiement/succes?session_id=' . $session);
        self::assertSame('pending', $this->status()['order_status']);

        FakeGateway::pay($session, 15998);
        $this->client->request('GET', '/paiement/succes?session_id=' . $session);
        self::assertSame('processing', $this->status()['order_status']);
    }

    /**
     * Régression : relancer le paiement d'une commande créait une seconde ligne de paiement
     * et butait sur la contrainte d'unicité. Un nouvel essai remplace désormais la session.
     */
    public function testNouvelEssaiDePaiement(): void
    {
        $this->orderOf();
        $first = FakeGateway::lastSessionId();

        // Afficher la page ne crée rien chez le prestataire.
        $this->client->request('GET', '/paiement/commande/' . $this->number);
        self::assertResponseRedirects('/commande/' . $this->orderId);
        self::assertSame($first, FakeGateway::lastSessionId());

        $this->client->request('POST', '/paiement/commande/' . $this->number);
        self::assertResponseStatusCodeSame(403, 'Le paiement exige le jeton du formulaire.');

        $crawler = $this->client->request('GET', '/commande/' . $this->orderId);
        $this->client->submit($crawler->filter('form[action^="/paiement/commande/"]')->form());
        $second = FakeGateway::lastSessionId();

        self::assertNotSame($first, $second);
        self::assertResponseRedirects('https://checkout.stripe.com/c/pay/' . $second);
        self::assertSame(1, (int) $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM payment'));

        // L'ancienne session ne correspond plus à aucune commande.
        FakeGateway::pay($first);
        $this->client->request('GET', '/paiement/succes?session_id=' . $first);
        self::assertSame('pending', $this->status()['order_status']);

        FakeGateway::pay($second);
        $this->client->request('GET', '/paiement/succes?session_id=' . $second);
        self::assertSame('processing', $this->status()['order_status']);
    }

    public function testNotificationStripeSignee(): void
    {
        $this->orderOf();
        $session = FakeGateway::lastSessionId();
        FakeGateway::pay($session);

        // Stripe appelle ce point sans session ni cookie.
        $this->client->getCookieJar()->clear();
        $anonymous = $this->client;

        $anonymous->request('POST', '/stripe/webhook', [], [], ['HTTP_STRIPE_SIGNATURE' => 'fausse-signature'], $session);
        self::assertSame(400, $anonymous->getResponse()->getStatusCode());
        self::assertSame('pending', $this->status()['order_status']);

        $anonymous->request('POST', '/stripe/webhook', [], [], ['HTTP_STRIPE_SIGNATURE' => 'signature-valide'], $session);
        self::assertSame(200, $anonymous->getResponse()->getStatusCode());
        self::assertSame('processing', $this->status()['order_status']);

        FakeGateway::$webhooks = false;
        $anonymous->request('POST', '/stripe/webhook', [], [], ['HTTP_STRIPE_SIGNATURE' => 'signature-valide'], $session);
        self::assertSame(404, $anonymous->getResponse()->getStatusCode(), 'Sans secret de signature, le point de notification n\'existe pas.');
    }

    /** Commande annulée faute de paiement, puis finalement payée : elle repart et reprend son stock. */
    public function testPaiementTardifApresExpiration(): void
    {
        $this->orderOf('30.00', 4);
        $session = FakeGateway::lastSessionId();

        static::getContainer()->get(OrderService::class)->expireUnpaidOrders(new \DateTimeImmutable('+3 hours'));
        self::assertSame('cancelled', $this->status()['order_status']);
        self::assertSame(10, $this->stockOf($this->product));

        FakeGateway::pay($session);
        $this->client->request('GET', '/paiement/succes?session_id=' . $session);

        self::assertSame('processing', $this->status()['order_status']);
        self::assertSame(6, $this->stockOf($this->product));
    }

    public function testSansClesDePaiementLaCommandeResteEnAttente(): void
    {
        FakeGateway::$configured = false;
        $this->orderOf();

        self::assertResponseRedirects('/commande/' . $this->orderId);
        $crawler = $this->client->followRedirect();
        self::assertStringContainsString('Votre commande est enregistrée', $crawler->filter('.flash--warning')->text());
        self::assertSame(0, (int) $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM payment'));
    }
}
