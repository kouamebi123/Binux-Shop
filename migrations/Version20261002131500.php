<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261002131500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed the Binux Shop catalog with the canonical categories, products and matching images';
    }

    public function up(Schema $schema): void
    {
        // Delete existing seeded data to allow re-runs
        $this->connection->executeStatement('DELETE FROM product WHERE slug IN (
            "iphone-15-pro", "macbook-air-m2", "airpods-pro-2", "samsung-galaxy-s24-ultra",
            "veste-en-cuir-premium", "sneakers-tendance", "sac-a-main-designer",
            "canape-scandinave-3-places", "lampe-design-led", "tapis-moderne-200x300cm",
            "velo-de-course-pro", "tapis-de-yoga-premium", "kit-soins-visage-complet",
            "parfum-de-luxe-100ml", "coffret-harry-potter-integral", "console-de-jeux-retro"
        )');

        $this->connection->executeStatement('DELETE FROM category WHERE slug IN (
            "electronique", "mode", "maison-decoration", "sports-loisirs", "beaute-sante", "livres-culture"
        )');

        // Insert categories
        $categories = [
            ['slug' => 'electronique', 'name' => 'Électronique', 'description' => 'Smartphones, ordinateurs, tablettes et accessoires high-tech', 'image' => 'https://images.unsplash.com/photo-1498049794561-7780e7231661?w=400&h=200&fit=crop'],
            ['slug' => 'mode', 'name' => 'Mode', 'description' => 'Vêtements, chaussures et accessoires pour homme et femme', 'image' => 'https://images.unsplash.com/photo-1441986300917-64674bd600d8?w=400&h=200&fit=crop'],
            ['slug' => 'maison-decoration', 'name' => 'Maison & Décoration', 'description' => 'Meubles, décoration et accessoires pour la maison', 'image' => 'https://images.unsplash.com/photo-1586023492125-27b2c045efd7?w=400&h=200&fit=crop'],
            ['slug' => 'sports-loisirs', 'name' => 'Sports & Loisirs', 'description' => 'Équipements sportifs et articles de loisirs', 'image' => 'https://images.unsplash.com/photo-1461896836934-ffe607ba8211?w=400&h=200&fit=crop'],
            ['slug' => 'beaute-sante', 'name' => 'Beauté & Santé', 'description' => 'Produits de beauté, cosmétiques et bien-être', 'image' => 'https://images.unsplash.com/photo-1596755389378-c31d21fd1273?w=400&h=200&fit=crop'],
            ['slug' => 'livres-culture', 'name' => 'Livres & Culture', 'description' => 'Livres, musique, films et jeux vidéo', 'image' => 'https://images.unsplash.com/photo-1495446815901-a7297e633e8d?w=400&h=200&fit=crop'],
        ];

        foreach ($categories as $cat) {
            $this->connection->insert('category', [
                'name' => $cat['name'],
                'slug' => $cat['slug'],
                'description' => $cat['description'],
                'image' => $cat['image'],
                'image_name' => null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        // Fetch category IDs
        $catIds = [];
        foreach (['electronique', 'mode', 'maison-decoration', 'sports-loisirs', 'beaute-sante', 'livres-culture'] as $slug) {
            $id = $this->connection->fetchOne('SELECT id FROM category WHERE slug = ?', [$slug]);
            $catIds[$slug] = (int) $id;
        }

        // Insert products
        $products = [
            ['slug' => 'iphone-15-pro', 'name' => 'iPhone 15 Pro', 'description' => 'Dernier iPhone avec puce A17 Pro, écran Super Retina XDR et appareil photo professionnel 48 MP', 'price' => '1199.99', 'old_price' => '1299.99', 'stock' => 25, 'cat' => 'electronique', 'image' => 'https://media.tatacroma.com/Croma%20Assets/Communication/Mobiles/Images/300749_0_hyore5.png', 'featured' => 1],
            ['slug' => 'macbook-air-m2', 'name' => 'MacBook Air M2', 'description' => 'Ordinateur portable ultra-fin avec puce M2, écran Retina 13.6 pouces et autonomie de 18h', 'price' => '1299.99', 'old_price' => '1499.99', 'stock' => 15, 'cat' => 'electronique', 'image' => 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?w=600&h=500&fit=crop', 'featured' => 1],
            ['slug' => 'airpods-pro-2', 'name' => 'AirPods Pro 2', 'description' => 'Écouteurs sans fil avec réduction active du bruit et audio spatial personnalisé', 'price' => '279.99', 'old_price' => null, 'stock' => 50, 'cat' => 'electronique', 'image' => 'https://images.unsplash.com/photo-1606841837239-c5a1a4a07af7?w=600&h=500&fit=crop', 'featured' => 0],
            ['slug' => 'samsung-galaxy-s24-ultra', 'name' => 'Samsung Galaxy S24 Ultra', 'description' => 'Smartphone haut de gamme avec S Pen, écran AMOLED 6.8" et appareil photo 200 MP', 'price' => '1399.99', 'old_price' => null, 'stock' => 20, 'cat' => 'electronique', 'image' => 'https://images.unsplash.com/photo-1610945415295-d9bbf067e59c?w=600&h=500&fit=crop', 'featured' => 1],
            ['slug' => 'veste-en-cuir-premium', 'name' => 'Veste en Cuir Premium', 'description' => 'Veste en cuir véritable de haute qualité, coupe moderne et élégante', 'price' => '299.99', 'old_price' => '399.99', 'stock' => 12, 'cat' => 'mode', 'image' => 'https://www.espace-des-marques.com/media/cache/shop_product_original/a5/50/e9124ae5bddacd600fab4893cd67.jpg', 'featured' => 1],
            ['slug' => 'sneakers-tendance', 'name' => 'Sneakers Tendance', 'description' => 'Chaussures de sport confortables et stylées, parfaites pour un look décontracté', 'price' => '89.99', 'old_price' => null, 'stock' => 40, 'cat' => 'mode', 'image' => 'https://images.unsplash.com/photo-1549298916-b41d501d3772?w=600&h=500&fit=crop', 'featured' => 0],
            ['slug' => 'sac-a-main-designer', 'name' => 'Sac à Main Designer', 'description' => 'Sac à main élégant en cuir synthétique de qualité avec multiples compartiments', 'price' => '149.99', 'old_price' => '199.99', 'stock' => 18, 'cat' => 'mode', 'image' => 'https://images.unsplash.com/photo-1584917865442-de89df76afd3?w=600&h=500&fit=crop', 'featured' => 0],
            ['slug' => 'canape-scandinave-3-places', 'name' => 'Canapé Scandinave 3 Places', 'description' => 'Canapé moderne et confortable avec revêtement en tissu haut de gamme', 'price' => '799.99', 'old_price' => '999.99', 'stock' => 8, 'cat' => 'maison-decoration', 'image' => 'https://www.nordicaustraliacollections.com.au/cdn/shop/files/V315-VOL-SINA-03-155553-00.jpg?v=1711953501', 'featured' => 1],
            ['slug' => 'lampe-design-led', 'name' => 'Lampe Design LED', 'description' => 'Lampe de table moderne avec LED intégrées et variation d\'intensité', 'price' => '79.99', 'old_price' => null, 'stock' => 30, 'cat' => 'maison-decoration', 'image' => 'https://images.unsplash.com/photo-1507473885765-e6ed057f782c?w=600&h=500&fit=crop', 'featured' => 0],
            ['slug' => 'tapis-moderne-200x300cm', 'name' => 'Tapis Moderne 200x300cm', 'description' => 'Tapis décoratif doux et résistant, parfait pour votre salon', 'price' => '199.99', 'old_price' => null, 'stock' => 15, 'cat' => 'maison-decoration', 'image' => 'https://images.unsplash.com/photo-1600166898405-da9535204843?w=600&h=500&fit=crop', 'featured' => 0],
            ['slug' => 'velo-de-course-pro', 'name' => 'Vélo de Course Pro', 'description' => 'Vélo de course léger en carbone avec groupe Shimano 105', 'price' => '1499.99', 'old_price' => null, 'stock' => 5, 'cat' => 'sports-loisirs', 'image' => 'https://images.unsplash.com/photo-1485965120184-e220f721d03e?w=600&h=500&fit=crop', 'featured' => 1],
            ['slug' => 'tapis-de-yoga-premium', 'name' => 'Tapis de Yoga Premium', 'description' => 'Tapis de yoga antidérapant et confortable, idéal pour vos séances', 'price' => '49.99', 'old_price' => '69.99', 'stock' => 35, 'cat' => 'sports-loisirs', 'image' => 'https://www.gaiam.com/cdn/shop/products/05-64061_6MM-GAIAM-ESSENTIALS-YOGA-MAT-TEAL_C_600x.jpg?v=1668557105', 'featured' => 0],
            ['slug' => 'kit-soins-visage-complet', 'name' => 'Kit Soins Visage Complet', 'description' => 'Ensemble complet de soins du visage avec produits naturels et bio', 'price' => '89.99', 'old_price' => null, 'stock' => 25, 'cat' => 'beaute-sante', 'image' => 'https://images.unsplash.com/photo-1556228720-195a672e8a03?w=600&h=500&fit=crop', 'featured' => 0],
            ['slug' => 'parfum-de-luxe-100ml', 'name' => 'Parfum de Luxe 100ml', 'description' => 'Parfum haut de gamme aux notes florales et boisées', 'price' => '129.99', 'old_price' => '159.99', 'stock' => 20, 'cat' => 'beaute-sante', 'image' => 'https://images.unsplash.com/photo-1541643600914-78b084683601?w=600&h=500&fit=crop', 'featured' => 1],
            ['slug' => 'coffret-harry-potter-integral', 'name' => 'Coffret Harry Potter Intégral', 'description' => 'Édition collector avec les 7 tomes de la saga Harry Potter', 'price' => '99.99', 'old_price' => null, 'stock' => 22, 'cat' => 'livres-culture', 'image' => 'https://images.unsplash.com/photo-1544947950-fa07a98d237f?w=600&h=500&fit=crop', 'featured' => 0],
            ['slug' => 'console-de-jeux-retro', 'name' => 'Console de Jeux Rétro', 'description' => 'Console vintage avec 500 jeux classiques intégrés', 'price' => '79.99', 'old_price' => null, 'stock' => 18, 'cat' => 'livres-culture', 'image' => 'https://fleuurs.it/cdn/shop/files/66f27a7af9760f370e699b554642bad5_700x700.jpg?v=1736334449', 'featured' => 0],
        ];

        $now = date('Y-m-d H:i:s');
        foreach ($products as $prod) {
            $this->connection->insert('product', [
                'name' => $prod['name'],
                'slug' => $prod['slug'],
                'description' => $prod['description'],
                'price' => $prod['price'],
                'old_price' => $prod['old_price'],
                'stock' => $prod['stock'],
                'image' => $prod['image'],
                'image_name' => null,
                'images' => null,
                'is_active' => 1,
                'is_featured' => $prod['featured'],
                'category_id' => $catIds[$prod['cat']],
                'created_at' => $now,
                'updated_at' => null,
            ]);
        }
    }

    public function down(Schema $schema): void
    {
        // Intentionally left empty: catalog data must not be deleted on rollback.
    }
}

