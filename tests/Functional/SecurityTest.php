<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Tests\Support\ShopWebTestCase;

class SecurityTest extends ShopWebTestCase
{
    public function testEnTetesDeSecurite(): void
    {
        $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();

        $csp = (string) $this->client->getResponse()->headers->get('Content-Security-Policy');
        self::assertStringContainsString("default-src 'self'", $csp);
        self::assertStringContainsString("script-src 'self';", $csp);
        self::assertStringContainsString("style-src 'self';", $csp);
        self::assertStringContainsString("frame-ancestors 'none'", $csp);
        self::assertStringNotContainsString('unsafe-inline', $csp);

        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        self::assertResponseHeaderSame('X-Frame-Options', 'DENY');
        self::assertResponseHeaderSame('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    /** La politique interdit styles et scripts en ligne : aucune page ne doit en contenir. */
    public function testAucunStyleNiScriptEnLigne(): void
    {
        $product = $this->createProduct();
        $this->client->loginUser($this->createUser());

        foreach (['/', '/produits/', '/produits/' . $product->getSlug(), '/connexion', '/panier/', '/compte/', '/a-propos'] as $path) {
            $html = (string) $this->client->request('GET', $path)->html();
            self::assertDoesNotMatchRegularExpression('/\sstyle\s*=/i', $html, $path);
            self::assertDoesNotMatchRegularExpression('/<style\b/i', $html, $path);
            self::assertDoesNotMatchRegularExpression('/<script(?![^>]*\ssrc=)/i', $html, $path);
            self::assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=\s*"/i', $html, $path);
        }
    }

    public function testControleDeSante(): void
    {
        $this->client->request('GET', '/sante');
        self::assertResponseIsSuccessful();
        self::assertSame(['status' => 'ok'], json_decode((string) $this->client->getResponse()->getContent(), true));
    }

    /**
     * @dataProvider protectedPaths
     */
    public function testPagesReserveesAuxClientsConnectes(string $path): void
    {
        $this->client->request('GET', $path);
        self::assertResponseRedirects('/connexion');
    }

    public static function protectedPaths(): iterable
    {
        yield ['/panier/'];
        yield ['/commande/creer'];
        yield ['/commande/mes-commandes'];
        yield ['/commande/1'];
        yield ['/compte/'];
        yield ['/compte/mot-de-passe'];
        yield ['/paiement/succes?session_id=cs_test_x'];
        yield ['/admin/'];
        yield ['/admin/produits'];
        yield ['/admin/commandes'];
    }

    public function testUnClientNAccedePasALAdministration(): void
    {
        $this->client->loginUser($this->createUser());
        $this->client->request('GET', '/admin/');
        self::assertResponseStatusCodeSame(403);
    }

    public function testConnexionEtProtectionContreLaForceBrute(): void
    {
        $this->createUser('camille@exemple.test');

        $crawler = $this->client->request('GET', '/connexion');
        $this->client->submit($crawler->selectButton('Se connecter')->form(['_username' => 'camille@exemple.test', '_password' => self::PASSWORD]));
        self::assertResponseRedirects('/');
        $this->client->request('GET', '/deconnexion');
        self::assertResponseStatusCodeSame(403, 'La déconnexion exige son jeton.');

        // Cinq échecs, puis le compte est mis en attente même avec le bon mot de passe.
        $this->client->getCookieJar()->clear();
        for ($i = 0; $i < 5; ++$i) {
            $crawler = $this->client->request('GET', '/connexion');
            $this->client->submit($crawler->selectButton('Se connecter')->form(['_username' => 'camille@exemple.test', '_password' => 'mauvais-mot-de-passe']));
            $this->client->followRedirect();
        }

        $crawler = $this->client->request('GET', '/connexion');
        $this->client->submit($crawler->selectButton('Se connecter')->form(['_username' => 'camille@exemple.test', '_password' => self::PASSWORD]));
        $crawler = $this->client->followRedirect();
        self::assertStringContainsString('tentatives', $crawler->filter('.field__errors')->text());
    }

    public function testRetourApresConnexionLimiteAuSite(): void
    {
        $crawler = $this->client->request('GET', '/connexion?retour=/produits/iphone-15-pro');
        self::assertSame('/produits/iphone-15-pro', $crawler->filter('input[name="_target_path"]')->attr('value'));

        foreach (['https://exemple.test/piege', '//exemple.test/piege', '/\\exemple.test'] as $target) {
            $crawler = $this->client->request('GET', '/connexion?retour=' . urlencode($target));
            self::assertCount(0, $crawler->filter('input[name="_target_path"]'), $target);
        }
    }

    public function testInscription(): void
    {
        $crawler = $this->client->request('GET', '/inscription');
        $form = $crawler->filter('form[name="registration"]')->form([
            'registration[firstName]' => 'Camille',
            'registration[lastName]' => 'Martin',
            'registration[email]' => 'Camille@Exemple.test',
            'registration[plainPassword][first]' => 'azerty',
            'registration[plainPassword][second]' => 'azerty',
        ]);
        $crawler = $this->client->submit($form);
        self::assertStringContainsString('au moins 10 caractères', $crawler->filter('.field__errors')->text());

        $form = $crawler->filter('form[name="registration"]')->form([
            'registration[plainPassword][first]' => self::PASSWORD,
            'registration[plainPassword][second]' => self::PASSWORD,
        ]);
        $this->client->submit($form);
        self::assertResponseRedirects('/connexion');

        $user = $this->em()->getRepository(User::class)->findOneBy(['email' => 'camille@exemple.test']);
        self::assertNotNull($user, 'L\'adresse e-mail est enregistrée en minuscules.');
        self::assertSame(['ROLE_USER'], $user->getRoles());
        self::assertNotSame(self::PASSWORD, $user->getPassword());
    }

    public function testInstallationDeLAdministrateur(): void
    {
        $crawler = $this->client->request('GET', '/installation');
        self::assertResponseIsSuccessful();

        $fields = ['email' => 'admin@exemple.test', 'password' => 'Vitrine-allumee-2026!', 'password_confirm' => 'Vitrine-allumee-2026!'];

        $crawler = $this->client->submit($crawler->selectButton('Créer le compte administrateur')->form($fields + ['token' => 'mauvais-code']));
        self::assertStringContainsString('code d\'installation est incorrect', $crawler->filter('.field__errors')->text());
        self::assertNull($this->em()->getRepository(User::class)->findOneBy(['email' => 'admin@exemple.test']));

        $crawler = $this->client->submit($crawler->selectButton('Créer le compte administrateur')->form(['token' => 'code-d-installation-pour-les-tests', 'email' => 'admin@exemple.test', 'password' => 'motdepasse12', 'password_confirm' => 'motdepasse12']));
        self::assertStringContainsString('trop facile à deviner', $crawler->filter('.field__errors')->text());

        $this->client->submit($crawler->selectButton('Créer le compte administrateur')->form($fields + ['token' => 'code-d-installation-pour-les-tests']));
        self::assertResponseRedirects('/connexion');

        $admin = $this->em()->getRepository(User::class)->findOneBy(['email' => 'admin@exemple.test']);
        self::assertContains('ROLE_ADMIN', $admin->getRoles());
    }

    public function testInstallationLimiteeEnNombreDEssais(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $crawler = $this->client->request('GET', '/installation');
            $this->client->submit($crawler->selectButton('Créer le compte administrateur')->form(['token' => 'essai-' . $i, 'email' => 'a@exemple.test', 'password' => 'x', 'password_confirm' => 'x']));
        }

        $crawler = $this->client->request('GET', '/installation');
        $crawler = $this->client->submit($crawler->selectButton('Créer le compte administrateur')->form(['token' => 'code-d-installation-pour-les-tests', 'email' => 'admin@exemple.test', 'password' => 'Vitrine-allumee-2026!', 'password_confirm' => 'Vitrine-allumee-2026!']));
        self::assertStringContainsString('Trop d\'essais', $crawler->filter('.field__errors')->text());
        self::assertNull($this->em()->getRepository(User::class)->findOneBy(['email' => 'admin@exemple.test']));
    }
}
