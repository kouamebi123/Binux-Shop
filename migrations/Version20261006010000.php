<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Les identifiants des comptes de démonstration ont été publiés dans le dépôt (README, jeu de données).
 * Tout compte qui utilise encore l'un de ces mots de passe est verrouillé : son mot de passe est remplacé
 * par une valeur aléatoire que personne ne connaît, et le rôle administrateur lui est retiré.
 * Un compte dont le mot de passe a déjà été changé n'est pas touché.
 */
final class Version20261006010000 extends AbstractMigration
{
    private const PUBLISHED = [
        'admin@e-shop.com' => 'admin123',
        'client@e-shop.com' => 'client123',
        'admin@binuxshop.com' => 'admin123',
        'client@binuxshop.com' => 'client123',
    ];

    public function getDescription(): string
    {
        return 'Verrouille les comptes de démonstration dont le mot de passe publié est encore actif';
    }

    public function up(Schema $schema): void
    {
        $locked = 0;

        foreach (self::PUBLISHED as $email => $password) {
            $row = $this->connection->fetchAssociative('SELECT id, password FROM "user" WHERE LOWER(email) = :email', ['email' => $email]);

            if (false === $row || !password_verify($password, (string) $row['password'])) {
                continue;
            }

            $this->connection->executeStatement(
                'UPDATE "user" SET password = :password, roles = :roles WHERE id = :id',
                [
                    'password' => password_hash(bin2hex(random_bytes(32)), \PASSWORD_BCRYPT),
                    'roles' => '[]',
                    'id' => $row['id'],
                ]
            );
            ++$locked;
        }

        $this->warnIf($locked > 0, sprintf('Comptes de démonstration verrouillés : %d.', $locked));
        $this->write(sprintf('Comptes de démonstration verrouillés : %d.', $locked));
    }

    public function down(Schema $schema): void
    {
        // Irréversible par nature : les anciens mots de passe étaient publics.
    }
}
