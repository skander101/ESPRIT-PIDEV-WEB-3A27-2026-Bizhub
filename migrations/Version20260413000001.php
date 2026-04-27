<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajout des champs Stripe, score, et table historique des statuts.
 * Intégration non destructive — les champs existants sont conservés.
 */
final class Version20260413000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'BizHub: Stripe fields, auto-scoring, and commande_status_history table';
    }

    public function up(Schema $schema): void
    {
        // 1. Nouveaux champs sur la table commande
        $this->addSql("
            ALTER TABLE commande
                ADD COLUMN stripe_session_id VARCHAR(255) DEFAULT NULL,
                ADD COLUMN stripe_payment_intent_id VARCHAR(255) DEFAULT NULL,
                ADD COLUMN score_auto INT DEFAULT NULL
        ");

        // 2. Mise à jour du choix de statuts (informatif — MySQL accepte la nouvelle valeur)
        // Les nouveaux statuts 'en_cours_paiement', 'payee', 'en_preparation' sont des strings,
        // pas besoin de modifier le schéma MySQL sauf si la colonne est un ENUM.
        // Vérification : si la colonne statut est un ENUM, modifier ici. Sinon, rien à faire.

        // 3. Nouvelle table commande_status_history
        $this->addSql("
            CREATE TABLE IF NOT EXISTS commande_status_history (
                id INT AUTO_INCREMENT NOT NULL,
                commande_id INT NOT NULL,
                statut_precedent VARCHAR(50) DEFAULT NULL,
                statut_nouveau VARCHAR(50) NOT NULL,
                changed_at DATETIME NOT NULL,
                changed_by_user_id INT DEFAULT NULL,
                note LONGTEXT DEFAULT NULL,
                PRIMARY KEY (id),
                INDEX IDX_history_commande (commande_id),
                CONSTRAINT FK_commande_history FOREIGN KEY (commande_id)
                    REFERENCES commande (commande_id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS commande_status_history');
        $this->addSql('
            ALTER TABLE commande
                DROP COLUMN stripe_session_id,
                DROP COLUMN stripe_payment_intent_id,
                DROP COLUMN score_auto
        ');
    }
}
