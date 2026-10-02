<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261002132500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Validate canonical Binux Shop catalog seed';
    }

    public function up(Schema $schema): void
    {
        $categorySlugs = [
            'electronique',
            'mode',
            'maison-decoration',
            'sports-loisirs',
            'beaute-sante',
            'livres-culture',
        ];

        $productSlugs = [
            'iphone-15-pro',
            'macbook-air-m2',
            'airpods-pro-2',
            'samsung-galaxy-s24-ultra',
            'veste-en-cuir-premium',
            'sneakers-tendance',
            'sac-a-main-designer',
            'canape-scandinave-3-places',
            'lampe-design-led',
            'tapis-moderne-200x300cm',
            'velo-de-course-pro',
            'tapis-de-yoga-premium',
            'kit-soins-visage-complet',
            'parfum-de-luxe-100ml',
            'coffret-harry-potter-integral',
            'console-de-jeux-retro',
        ];

        $categoryCount = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM category WHERE slug IN (?)',
            [$categorySlugs],
            [\Doctrine\DBAL\ArrayParameterType::STRING]
        );

        $productCount = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM product WHERE slug IN (?)',
            [$productSlugs],
            [\Doctrine\DBAL\ArrayParameterType::STRING]
        );

        $categoryImages = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM category WHERE slug IN (?) AND image IS NOT NULL AND image <> \'\'',
            [$categorySlugs],
            [\Doctrine\DBAL\ArrayParameterType::STRING]
        );

        $productImages = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM product WHERE slug IN (?) AND image IS NOT NULL AND image <> \'\'',
            [$productSlugs],
            [\Doctrine\DBAL\ArrayParameterType::STRING]
        );

        $this->abortIf($categoryCount !== 6, sprintf('Catalog validation failed: expected 6 categories, found %d.', $categoryCount));
        $this->abortIf($productCount !== 16, sprintf('Catalog validation failed: expected 16 products, found %d.', $productCount));
        $this->abortIf($categoryImages !== 6, sprintf('Catalog validation failed: expected 6 category images, found %d.', $categoryImages));
        $this->abortIf($productImages !== 16, sprintf('Catalog validation failed: expected 16 product images, found %d.', $productImages));
    }

    public function down(Schema $schema): void
    {
    }
}
