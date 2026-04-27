<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260422000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add tracking_number and tracking_carrier to commande table (Shippo integration)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commande ADD tracking_number VARCHAR(255) DEFAULT NULL, ADD tracking_carrier VARCHAR(100) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commande DROP COLUMN tracking_number, DROP COLUMN tracking_carrier');
    }
}
