<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add new statut values to commande ENUM';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE commande MODIFY COLUMN statut ENUM('en_attente', 'confirmee', 'en_cours_paiement', 'payee', 'en_preparation', 'annulee', 'livree') NOT NULL DEFAULT 'en_attente'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE commande MODIFY COLUMN statut ENUM('en_attente', 'confirmee', 'annulee', 'livree') NOT NULL DEFAULT 'en_attente'");
    }
}
