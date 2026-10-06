<?php

namespace App\Tests\Functional;

use App\Entity\Order;
use App\Entity\Product;
use App\Exception\ShopException;
use App\Service\OrderService;
use App\Tests\Support\FakeGateway;
use App\Tests\Support\ShopWebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class AdminTest extends ShopWebTestCase
{
    private function order(Product $product, int $quantity, bool $paid): int
    {
        $this->client->loginUser($this->createUser('client-' . uniqid() . '@exemple.test'));
        $this->addToCart($product, $quantity);
        $number = $this->placeOrder();

        if ($paid) {
            FakeGateway::pay(FakeGateway::lastSessionId());
            $this->client->request('GET', '/paiement/succes?session_id=' . FakeGateway::lastSessionId());
        }

        return (int) $this->em()->getConnection()->fetchOne('SELECT id FROM "order" WHERE order_number = ?', [$number]);
    }

    private function setStatus(int $orderId, string $status): void
    {
        $crawler = $this->client->request('GET', '/admin/commande/' . $orderId);
        $token = $crawler->filter('input[name="_token"]')->attr('value');
        $this->client->request('POST', '/admin/commande/' . $orderId . '/statut', ['status' => $status, '_token' => $token]);
    }

    /** Une commande terminée n'offre plus de formulaire, et le service refuse tout changement. */
    private function assertFinal(int $orderId): void
    {
        $crawler = $this->client->request('GET', '/admin/commande/' . $orderId);
        self::assertCount(0, $crawler->filter('form[action$="/statut"]'));

        $service = static::getContainer()->get(OrderService::class);
        $order = $this->em()->getRepository(Order::class)->find($orderId);
        self::assertSame([], $service->allowedTransitions($order));

        foreach (array_keys(Order::getAvailableStatuses()) as $status) {
            if ($status === $order->getStatus()) {
                continue;
            }
            try {
                $service->updateOrderStatus($order, $status);
                self::fail('Le passage à ' . $status . ' aurait dû être refusé.');
            } catch (ShopException) {
            }
        }
    }

    private function statusOf(int $orderId): string
    {
        return (string) $this->em()->getConnection()->fetchOne('SELECT status FROM "order" WHERE id = ?', [$orderId]);
    }

    public function testTableauDeBordChiffres(): void
    {
        $product = $this->createProduct('79.99', 20);
        $this->order($product, 3, true);
        $this->order($product, 1, true);
        $this->order($product, 2, false);

        $this->client->loginUser($this->createUser('admin@exemple.test', ['ROLE_ADMIN']));
        $crawler = $this->client->request('GET', '/admin/');
        self::assertResponseIsSuccessful();

        // Encaissé : 3 × 79,99 + 79,99 = 319,96 € ; la commande non payée n'est pas comptée.
        self::assertStringContainsString("319,96\u{00A0}€", $crawler->filter('.stat--hero .stat__value')->text());
        self::assertStringContainsString("159,98\u{00A0}€", $crawler->filter('.stat')->eq(1)->filter('.stat__value')->text());
        self::assertSame('2', $crawler->filter('.stat')->eq(2)->filter('.stat__value')->text());
        self::assertSame('1', $crawler->filter('.stat')->eq(3)->filter('.stat__value')->text());
        self::assertCount(1, $crawler->filter('.chart__bar'));
    }

    public function testUneCommandeNonPayeeNeSePreparePas(): void
    {
        $product = $this->createProduct('30.00', 5);
        $orderId = $this->order($product, 2, false);

        $this->client->loginUser($this->createUser('admin@exemple.test', ['ROLE_ADMIN']));

        foreach (['processing', 'shipped', 'delivered'] as $status) {
            $this->setStatus($orderId, $status);
            self::assertSame('pending', $this->statusOf($orderId), $status);
        }

        // L'annulation reste possible et remet les articles en stock.
        self::assertSame(3, $this->stockOf($product));
        $this->setStatus($orderId, 'cancelled');
        self::assertSame('cancelled', $this->statusOf($orderId));
        self::assertSame(5, $this->stockOf($product));

        // Une commande annulée ne repart pas, et son stock n'est pas rendu deux fois.
        $this->assertFinal($orderId);
        self::assertSame('cancelled', $this->statusOf($orderId));
        self::assertSame(5, $this->stockOf($product));
    }

    public function testSuiviDUneCommandePayee(): void
    {
        $product = $this->createProduct('30.00', 5);
        $orderId = $this->order($product, 1, true);

        $this->client->loginUser($this->createUser('admin@exemple.test', ['ROLE_ADMIN']));

        $this->setStatus($orderId, 'delivered');
        self::assertSame('processing', $this->statusOf($orderId), 'On ne saute pas l\'expédition.');

        $this->setStatus($orderId, 'shipped');
        self::assertSame('shipped', $this->statusOf($orderId));
        $this->setStatus($orderId, 'delivered');
        self::assertSame('delivered', $this->statusOf($orderId));

        $this->assertFinal($orderId);
        self::assertSame('delivered', $this->statusOf($orderId), 'Une commande livrée ne s\'annule plus.');
        self::assertSame(4, $this->stockOf($product));
    }

    public function testChangementDeStatutExigeLeJeton(): void
    {
        $product = $this->createProduct('30.00', 5);
        $orderId = $this->order($product, 1, true);

        $this->client->loginUser($this->createUser('admin@exemple.test', ['ROLE_ADMIN']));
        $this->client->request('POST', '/admin/commande/' . $orderId . '/statut', ['status' => 'shipped']);
        self::assertResponseStatusCodeSame(403);
        self::assertSame('processing', $this->statusOf($orderId));
    }

    public function testUnProduitDejaCommandeEstRetireDeLaVentePasEfface(): void
    {
        $ordered = $this->createProduct('30.00', 5);
        $unused = $this->createProduct('10.00', 5);
        $this->order($ordered, 1, false);

        $this->client->loginUser($this->createUser('admin@exemple.test', ['ROLE_ADMIN']));
        $crawler = $this->client->request('GET', '/admin/produits');

        foreach ([$ordered, $unused] as $product) {
            $form = $crawler->filter(sprintf('form[action="/admin/produit/supprimer/%d"]', $product->getId()))->form();
            $this->client->submit($form);
            self::assertResponseRedirects('/admin/produits');
        }

        self::assertSame(false, $this->em()->getConnection()->fetchOne('SELECT is_active FROM product WHERE id = ?', [$ordered->getId()]));
        self::assertFalse($this->em()->getConnection()->fetchOne('SELECT id FROM product WHERE id = ?', [$unused->getId()]));

        // Retiré de la vente : la fiche publique n'existe plus.
        $this->client->request('GET', '/produits/' . $ordered->getSlug());
        self::assertResponseStatusCodeSame(404);
    }

    public function testProduitsHomonymesEtValidation(): void
    {
        $this->client->loginUser($this->createUser('admin@exemple.test', ['ROLE_ADMIN']));

        $create = function (array $fields) {
            $crawler = $this->client->request('GET', '/admin/produit/ajouter');
            $category = $crawler->filter('#product_category option')->eq(1)->attr('value');

            return $this->client->submit($crawler->filter('form[name="product"]')->form($fields + [
                'product[name]' => 'Test Lampe',
                'product[price]' => '49,90',
                'product[stock]' => '4',
                'product[category]' => $category,
            ]));
        };

        $create([]);
        self::assertResponseRedirects('/admin/produits');
        $create([]);
        self::assertResponseRedirects('/admin/produits', null, 'Deux produits du même nom ne provoquent plus d\'erreur.');
        self::assertSame(['test-lampe', 'test-lampe-2'], $this->em()->getConnection()->fetchFirstColumn("SELECT slug FROM product WHERE slug LIKE 'test-lampe%' ORDER BY id"));
        self::assertSame('49.90', $this->em()->getConnection()->fetchOne("SELECT price FROM product WHERE slug = 'test-lampe'"));

        $crawler = $create(['product[price]' => '-5', 'product[stock]' => '-1', 'product[image]' => 'javascript:alert(1)']);
        self::assertResponseStatusCodeSame(422);
        $errors = $crawler->filter('.field__errors')->each(fn ($node) => $node->text());
        self::assertCount(3, $errors, implode(' | ', $errors));
    }

    /** Un fichier qui n'est pas une image, ou qui se fait passer pour une image, n'est jamais enregistré. */
    public function testEnvoiDeFichierDangereuxRefuse(): void
    {
        $this->client->loginUser($this->createUser('admin@exemple.test', ['ROLE_ADMIN']));
        $dir = static::getContainer()->getParameter('kernel.project_dir') . '/public/images/products';
        $before = glob($dir . '/*') ?: [];

        $tmp = sys_get_temp_dir();
        file_put_contents($tmp . '/porte.php', "GIF89a<?php echo 'x'; ?>");
        file_put_contents($tmp . '/note.txt', 'ceci n\'est pas une image');
        file_put_contents($tmp . '/faux.png', "<?php echo 'x'; ?>");

        foreach (['porte.php' => 'image/gif', 'note.txt' => 'text/plain', 'faux.png' => 'image/png'] as $name => $mime) {
            $crawler = $this->client->request('GET', '/admin/produit/ajouter');
            $form = $crawler->filter('form[name="product"]')->form([
                'product[name]' => 'Test envoi',
                'product[price]' => '10',
                'product[stock]' => '1',
                'product[category]' => $crawler->filter('#product_category option')->eq(1)->attr('value'),
            ]);
            $form['product[imageFile][file]']->upload($tmp . '/' . $name);
            $this->client->submit($form);

            self::assertResponseStatusCodeSame(422, $name);
        }

        self::assertSame($before, glob($dir . '/*') ?: [], 'Aucun fichier refusé ne doit atterrir dans le dossier public.');
        self::assertSame(0, (int) $this->em()->getConnection()->fetchOne("SELECT COUNT(*) FROM product WHERE name = 'Test envoi'"));
    }

    public function testEnvoiDUneVraieImage(): void
    {
        $this->client->loginUser($this->createUser('admin@exemple.test', ['ROLE_ADMIN']));
        $dir = static::getContainer()->getParameter('kernel.project_dir') . '/public/images/products';

        $png = sys_get_temp_dir() . '/Photo De Produit.PNG';
        $image = imagecreatetruecolor(8, 8);
        imagepng($image, $png);

        $crawler = $this->client->request('GET', '/admin/produit/ajouter');
        $form = $crawler->filter('form[name="product"]')->form([
            'product[name]' => 'Test image',
            'product[price]' => '10',
            'product[stock]' => '1',
            'product[category]' => $crawler->filter('#product_category option')->eq(1)->attr('value'),
        ]);
        $form['product[imageFile][file]']->upload($png);
        $this->client->submit($form);
        self::assertResponseRedirects('/admin/produits');

        $stored = (string) $this->em()->getConnection()->fetchOne("SELECT image_name FROM product WHERE name = 'Test image'");
        self::assertMatchesRegularExpression('/^[0-9a-f]{24}\.png$/', $stored, 'Nom tiré au hasard, extension déduite du contenu.');
        self::assertFileExists($dir . '/' . $stored);
        unlink($dir . '/' . $stored);
    }

    public function testCategorieNonVideNonSupprimable(): void
    {
        $this->client->loginUser($this->createUser('admin@exemple.test', ['ROLE_ADMIN']));
        $crawler = $this->client->request('GET', '/admin/categories');
        $form = $crawler->filter('form[action^="/admin/categorie/supprimer/"]')->first()->form();
        $this->client->submit($form);

        self::assertResponseRedirects('/admin/categories');
        self::assertSame(6, (int) $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM category'));
        self::assertStringContainsString('contient encore', $this->client->followRedirect()->filter('.flash--error')->text());
    }
}
