<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260414000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée la table facture (OneToOne sur commande)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE IF NOT EXISTS facture (
                id              INT AUTO_INCREMENT NOT NULL,
                commande_id     INT NOT NULL,
                numero_facture  VARCHAR(50)  NOT NULL,
                date_facture    DATETIME     NOT NULL,
                total_ht        DECIMAL(10,3) NOT NULL DEFAULT 0.000,
                total_tva       DECIMAL(10,3) NOT NULL DEFAULT 0.000,
                total_ttc       DECIMAL(10,3) NOT NULL DEFAULT 0.000,
                stripe_ref      VARCHAR(255)  DEFAULT NULL,
                created_at      DATETIME     NOT NULL,
                UNIQUE INDEX uniq_facture_commande  (commande_id),
                UNIQUE INDEX uniq_facture_numero    (numero_facture),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
        ');

        $this->addSql('
            ALTER TABLE facture
                ADD CONSTRAINT fk_facture_commande
                FOREIGN KEY (commande_id) REFERENCES commande (id_commande)
                ON DELETE CASCADE
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE facture DROP FOREIGN KEY fk_facture_commande');
        $this->addSql('DROP TABLE IF EXISTS facture');
    }
}
