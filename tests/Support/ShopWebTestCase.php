<?php

namespace App\Tests\Support;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

abstract class ShopWebTestCase extends WebTestCase
{
    protected const PASSWORD = 'Promenade-du-soir-42';

    protected KernelBrowser $client;
    private static int $sequence = 0;

    protected function setUp(): void
    {
        FakeGateway::reset();
        $this->client = static::createClient();

        $connection = $this->em()->getConnection();
        $connection->executeStatement('TRUNCATE payment, order_item, "order", cart_item, cart, address, "user" RESTART IDENTITY CASCADE');
        $connection->executeStatement("DELETE FROM product WHERE slug LIKE 'test-%'");
        $connection->executeStatement("DELETE FROM category WHERE slug LIKE 'test-%'");

        static::getContainer()->get('cache.rate_limiter')->clear();
    }

    protected function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    protected function createUser(string $email = 'camille@exemple.test', array $roles = []): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setFirstName('Camille');
        $user->setLastName('Martin');
        $user->setRoles($roles);
        $user->setPassword(static::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, self::PASSWORD));

        $this->em()->persist($user);
        $this->em()->flush();

        return $user;
    }

    protected function createProduct(string $price = '79.99', int $stock = 10, ?string $name = null): Product
    {
        $n = ++self::$sequence;
        $category = $this->em()->getRepository(Category::class)->findOneBy(['slug' => 'electronique']);

        $product = new Product();
        $product->setName($name ?? 'Article de test ' . $n);
        $product->setSlug('test-article-' . $n);
        $product->setPrice($price);
        $product->setStock($stock);
        $product->setCategory($category);
        $product->setIsActive(true);
        $product->setIsFeatured(false);

        $this->em()->persist($product);
        $this->em()->flush();

        return $product;
    }

    /** Stock d'un produit relu en base, sans passer par les objets déjà chargés. */
    protected function stockOf(Product $product): int
    {
        return (int) $this->em()->getConnection()->fetchOne('SELECT stock FROM product WHERE id = ?', [$product->getId()]);
    }

    /** Ajoute un article au panier en soumettant le vrai formulaire de la fiche produit. */
    protected function addToCart(Product $product, int $quantity = 1): void
    {
        $crawler = $this->client->request('GET', '/produits/' . $product->getSlug());
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form[data-cart-add]')->form(['quantity' => $quantity]);
        $this->client->submit($form);
    }

    /** Passe la commande du panier courant et renvoie son numéro. */
    protected function placeOrder(): string
    {
        $crawler = $this->client->request('GET', '/commande/creer');
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form[name="order"]')->form([
            'order[address][street]' => '12 rue des Lilas',
            'order[address][postalCode]' => '35000',
            'order[address][city]' => 'Rennes',
            'order[address][country]' => 'France',
        ]);
        $this->client->submit($form);

        return (string) $this->em()->getConnection()->fetchOne('SELECT order_number FROM "order" ORDER BY id DESC LIMIT 1');
    }
}
