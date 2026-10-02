<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251014115432 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add image_name columns for uploaded category and product images';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE category ADD COLUMN image_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE product ADD COLUMN image_name VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE category DROP COLUMN image_name');
        $this->addSql('ALTER TABLE product DROP COLUMN image_name');
    }
}
