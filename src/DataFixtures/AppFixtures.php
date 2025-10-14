<?php

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

class AppFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;
    private SluggerInterface $slugger;

    public function __construct(UserPasswordHasherInterface $passwordHasher, SluggerInterface $slugger)
    {
        $this->passwordHasher = $passwordHasher;
        $this->slugger = $slugger;
    }

    public function load(ObjectManager $manager): void
    {
        // Créer un utilisateur admin
        $admin = new User();
        $admin->setEmail('admin@binuxshop.com');
        $admin->setFirstName('Admin');
        $admin->setLastName('Binux Shop');
        $admin->setPhone('+33 1 23 45 67 89');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'admin123'));
        $manager->persist($admin);

        // Créer un utilisateur client
        $user = new User();
        $user->setEmail('client@binuxshop.com');
        $user->setFirstName('Jean');
        $user->setLastName('Dupont');
        $user->setPhone('+33 6 12 34 56 78');
        $user->setPassword($this->passwordHasher->hashPassword($user, 'client123'));
        $manager->persist($user);

        // Créer les catégories
        $categories = [
            [
                'name' => 'Électronique',
                'description' => 'Smartphones, ordinateurs, tablettes et accessoires high-tech',
                'image' => 'https://images.unsplash.com/photo-1498049794561-7780e7231661?w=400&h=200&fit=crop'
            ],
            [
                'name' => 'Mode',
                'description' => 'Vêtements, chaussures et accessoires pour homme et femme',
                'image' => 'https://images.unsplash.com/photo-1441986300917-64674bd600d8?w=400&h=200&fit=crop'
            ],
            [
                'name' => 'Maison & Décoration',
                'description' => 'Meubles, décoration et accessoires pour la maison',
                'image' => 'https://images.unsplash.com/photo-1586023492125-27b2c045efd7?w=400&h=200&fit=crop'
            ],
            [
                'name' => 'Sports & Loisirs',
                'description' => 'Équipements sportifs et articles de loisirs',
                'image' => 'https://images.unsplash.com/photo-1461896836934-ffe607ba8211?w=400&h=200&fit=crop'
            ],
            [
                'name' => 'Beauté & Santé',
                'description' => 'Produits de beauté, cosmétiques et bien-être',
                'image' => 'https://images.unsplash.com/photo-1596755389378-c31d21fd1273?w=400&h=200&fit=crop'
            ],
            [
                'name' => 'Livres & Culture',
                'description' => 'Livres, musique, films et jeux vidéo',
                'image' => 'https://images.unsplash.com/photo-1495446815901-a7297e633e8d?w=400&h=200&fit=crop'
            ],
        ];

        $categoryObjects = [];
        foreach ($categories as $categoryData) {
            $category = new Category();
            $category->setName($categoryData['name']);
            $category->setDescription($categoryData['description']);
            $category->setImage($categoryData['image']);
            $slug = $this->slugger->slug($categoryData['name'])->lower();
            $category->setSlug($slug);
            $manager->persist($category);
            $categoryObjects[] = $category;
        }

        // Créer les produits
        $products = [
            // Électronique
            [
                'name' => 'iPhone 15 Pro',
                'description' => 'Dernier iPhone avec puce A17 Pro, écran Super Retina XDR et appareil photo professionnel 48 MP',
                'price' => '1199.99',
                'oldPrice' => '1299.99',
                'stock' => 25,
                'category' => $categoryObjects[0],
                'image' => 'https://via.placeholder.com/300x250/667eea/ffffff?text=iPhone+15+Pro',
                'isFeatured' => true,
                'isActive' => true
            ],
            [
                'name' => 'MacBook Air M2',
                'description' => 'Ordinateur portable ultra-fin avec puce M2, écran Retina 13.6 pouces et autonomie de 18h',
                'price' => '1299.99',
                'oldPrice' => '1499.99',
                'stock' => 15,
                'category' => $categoryObjects[0],
                'image' => 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?w=300&h=250&fit=crop',
                'isFeatured' => true,
                'isActive' => true
            ],
            [
                'name' => 'AirPods Pro 2',
                'description' => 'Écouteurs sans fil avec réduction active du bruit et audio spatial personnalisé',
                'price' => '279.99',
                'stock' => 50,
                'category' => $categoryObjects[0],
                'image' => 'https://images.unsplash.com/photo-1606841837239-c5a1a4a07af7?w=300&h=250&fit=crop',
                'isFeatured' => false,
                'isActive' => true
            ],
            [
                'name' => 'Samsung Galaxy S24 Ultra',
                'description' => 'Smartphone haut de gamme avec S Pen, écran AMOLED 6.8" et appareil photo 200 MP',
                'price' => '1399.99',
                'stock' => 20,
                'category' => $categoryObjects[0],
                'image' => 'https://images.unsplash.com/photo-1610945415295-d9bbf067e59c?w=300&h=250&fit=crop',
                'isFeatured' => true,
                'isActive' => true
            ],

            // Mode
            [
                'name' => 'Veste en Cuir Premium',
                'description' => 'Veste en cuir véritable de haute qualité, coupe moderne et élégante',
                'price' => '299.99',
                'oldPrice' => '399.99',
                'stock' => 12,
                'category' => $categoryObjects[1],
                'image' => 'https://images.unsplash.com/photo-1551028719-00167b16eac5?w=300&h=250&fit=crop',
                'isFeatured' => true,
                'isActive' => true
            ],
            [
                'name' => 'Sneakers Tendance',
                'description' => 'Chaussures de sport confortables et stylées, parfaites pour un look décontracté',
                'price' => '89.99',
                'stock' => 40,
                'category' => $categoryObjects[1],
                'image' => 'https://images.unsplash.com/photo-1549298916-b41d501d3772?w=300&h=250&fit=crop',
                'isFeatured' => false,
                'isActive' => true
            ],
            [
                'name' => 'Sac à Main Designer',
                'description' => 'Sac à main élégant en cuir synthétique de qualité avec multiples compartiments',
                'price' => '149.99',
                'oldPrice' => '199.99',
                'stock' => 18,
                'category' => $categoryObjects[1],
                'image' => 'https://images.unsplash.com/photo-1584917865442-de89df76afd3?w=300&h=250&fit=crop',
                'isFeatured' => false,
                'isActive' => true
            ],

            // Maison & Décoration
            [
                'name' => 'Canapé Scandinave 3 Places',
                'description' => 'Canapé moderne et confortable avec revêtement en tissu haut de gamme',
                'price' => '799.99',
                'oldPrice' => '999.99',
                'stock' => 8,
                'category' => $categoryObjects[2],
                'image' => 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=300&h=250&fit=crop',
                'isFeatured' => true,
                'isActive' => true
            ],
            [
                'name' => 'Lampe Design LED',
                'description' => 'Lampe de table moderne avec LED intégrées et variation d\'intensité',
                'price' => '79.99',
                'stock' => 30,
                'category' => $categoryObjects[2],
                'image' => 'https://images.unsplash.com/photo-1507473885765-e6ed057f782c?w=300&h=250&fit=crop',
                'isFeatured' => false,
                'isActive' => true
            ],
            [
                'name' => 'Tapis Moderne 200x300cm',
                'description' => 'Tapis décoratif doux et résistant, parfait pour votre salon',
                'price' => '199.99',
                'stock' => 15,
                'category' => $categoryObjects[2],
                'image' => 'https://images.unsplash.com/photo-1600166898405-da9535204843?w=300&h=250&fit=crop',
                'isFeatured' => false,
                'isActive' => true
            ],

            // Sports & Loisirs
            [
                'name' => 'Vélo de Course Pro',
                'description' => 'Vélo de course léger en carbone avec groupe Shimano 105',
                'price' => '1499.99',
                'stock' => 5,
                'category' => $categoryObjects[3],
                'image' => 'https://images.unsplash.com/photo-1485965120184-e220f721d03e?w=300&h=250&fit=crop',
                'isFeatured' => true,
                'isActive' => true
            ],
            [
                'name' => 'Tapis de Yoga Premium',
                'description' => 'Tapis de yoga antidérapant et confortable, idéal pour vos séances',
                'price' => '49.99',
                'oldPrice' => '69.99',
                'stock' => 35,
                'category' => $categoryObjects[3],
                'image' => 'https://images.unsplash.com/photo-1601925260368-ae2f83cf8b7f?w=300&h=250&fit=crop',
                'isFeatured' => false,
                'isActive' => true
            ],

            // Beauté & Santé
            [
                'name' => 'Kit Soins Visage Complet',
                'description' => 'Ensemble complet de soins du visage avec produits naturels et bio',
                'price' => '89.99',
                'stock' => 25,
                'category' => $categoryObjects[4],
                'image' => 'https://images.unsplash.com/photo-1556228720-195a672e8a03?w=300&h=250&fit=crop',
                'isFeatured' => false,
                'isActive' => true
            ],
            [
                'name' => 'Parfum de Luxe 100ml',
                'description' => 'Parfum haut de gamme aux notes florales et boisées',
                'price' => '129.99',
                'oldPrice' => '159.99',
                'stock' => 20,
                'category' => $categoryObjects[4],
                'image' => 'https://images.unsplash.com/photo-1541643600914-78b084683601?w=300&h=250&fit=crop',
                'isFeatured' => true,
                'isActive' => true
            ],

            // Livres & Culture
            [
                'name' => 'Coffret Harry Potter Intégral',
                'description' => 'Édition collector avec les 7 tomes de la saga Harry Potter',
                'price' => '99.99',
                'stock' => 22,
                'category' => $categoryObjects[5],
                'image' => 'https://images.unsplash.com/photo-1544947950-fa07a98d237f?w=300&h=250&fit=crop',
                'isFeatured' => false,
                'isActive' => true
            ],
            [
                'name' => 'Console de Jeux Rétro',
                'description' => 'Console vintage avec 500 jeux classiques intégrés',
                'price' => '79.99',
                'stock' => 18,
                'category' => $categoryObjects[5],
                'image' => 'https://images.unsplash.com/photo-1486401899868-0e435ed85128?w=300&h=250&fit=crop',
                'isFeatured' => false,
                'isActive' => true
            ],
        ];

        foreach ($products as $productData) {
            $product = new Product();
            $product->setName($productData['name']);
            $product->setDescription($productData['description']);
            $product->setPrice($productData['price']);
            if (isset($productData['oldPrice'])) {
                $product->setOldPrice($productData['oldPrice']);
            }
            $product->setStock($productData['stock']);
            $product->setCategory($productData['category']);
            $product->setImage($productData['image']);
            $product->setIsFeatured($productData['isFeatured']);
            $product->setIsActive($productData['isActive']);
            $slug = $this->slugger->slug($productData['name'])->lower();
            $product->setSlug($slug);
            $manager->persist($product);
        }

        $manager->flush();
    }
}

