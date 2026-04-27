<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260423100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Participation: participation_status, transaction_id, certificate_path for payment & attestation flow.';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        if (!$sm->tablesExist(['participation'])) {
            return;
        }

        $columns = $sm->listTableColumns('participation');
        $names = array_map(static fn (string $k): string => strtolower($k), array_keys($columns));

        if (!in_array('participation_status', $names, true)) {
            $this->addSql("ALTER TABLE participation ADD participation_status VARCHAR(32) NOT NULL DEFAULT 'AWAITING_PAYMENT'");
            $this->addSql("UPDATE participation SET participation_status = 'PAID' WHERE UPPER(payment_status) = 'PAID'");
        }

        if (!in_array('transaction_id', $names, true)) {
            $this->addSql('ALTER TABLE participation ADD transaction_id VARCHAR(80) DEFAULT NULL');
        }

        if (!in_array('certificate_path', $names, true)) {
            $this->addSql('ALTER TABLE participation ADD certificate_path VARCHAR(500) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        $platform = $this->connection->getDatabasePlatform();
        if (!$sm->tablesExist(['participation'])) {
            return;
        }

        $columns = $sm->listTableColumns('participation');
        $names = array_map(static fn (string $k): string => strtolower($k), array_keys($columns));

        if (in_array('certificate_path', $names, true)) {
            $this->addSql('ALTER TABLE participation DROP COLUMN certificate_path');
        }
        if (in_array('transaction_id', $names, true)) {
            $this->addSql('ALTER TABLE participation DROP COLUMN transaction_id');
        }
        if (in_array('participation_status', $names, true)) {
            if ($platform instanceof AbstractMySQLPlatform) {
                $this->addSql('ALTER TABLE participation DROP COLUMN participation_status');
            } elseif ($platform instanceof PostgreSQLPlatform) {
                $this->addSql('ALTER TABLE participation DROP COLUMN participation_status');
            }
        }
    }
}
