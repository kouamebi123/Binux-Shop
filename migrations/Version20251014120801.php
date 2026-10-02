<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251014120801 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add payment table for PostgreSQL';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE payment (id SERIAL NOT NULL, order_ref_id INTEGER NOT NULL, stripe_payment_intent_id VARCHAR(255) NOT NULL, amount NUMERIC(10, 2) NOT NULL, currency VARCHAR(10) NOT NULL, status VARCHAR(50) NOT NULL, stripe_data JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, paid_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6D28840DFC72F97E ON payment (stripe_payment_intent_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6D28840DE238517C ON payment (order_ref_id)');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840DE238517C FOREIGN KEY (order_ref_id) REFERENCES "order" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE payment');
    }
}
