<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260422120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Formation: nullable lieu (500), latitude/longitude for présentielle locations.';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        $platform = $this->connection->getDatabasePlatform();

        if (!$sm->tablesExist(['formation'])) {
            return;
        }

        $columns = $sm->listTableColumns('formation');
        $names = array_map(static fn (string $k): string => strtolower($k), array_keys($columns));

        if (!in_array('latitude', $names, true)) {
            $this->addSql('ALTER TABLE formation ADD latitude NUMERIC(10, 7) DEFAULT NULL');
        }

        if (!in_array('longitude', $names, true)) {
            $this->addSql('ALTER TABLE formation ADD longitude NUMERIC(11, 7) DEFAULT NULL');
        }

        if (!in_array('lieu', $names, true)) {
            return;
        }

        if ($platform instanceof AbstractMySQLPlatform) {
            $this->addSql('ALTER TABLE formation MODIFY lieu VARCHAR(500) DEFAULT NULL');
        } elseif ($platform instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE formation ALTER COLUMN lieu TYPE VARCHAR(500)');
            $this->addSql('ALTER TABLE formation ALTER COLUMN lieu DROP NOT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        $platform = $this->connection->getDatabasePlatform();

        if (!$sm->tablesExist(['formation'])) {
            return;
        }

        $columns = $sm->listTableColumns('formation');
        $names = array_map(static fn (string $k): string => strtolower($k), array_keys($columns));

        if (in_array('latitude', $names, true)) {
            $this->addSql('ALTER TABLE formation DROP COLUMN latitude');
        }
        if (in_array('longitude', $names, true)) {
            $this->addSql('ALTER TABLE formation DROP COLUMN longitude');
        }

        if (in_array('lieu', $names, true)) {
            if ($platform instanceof AbstractMySQLPlatform) {
                $this->addSql("UPDATE formation SET lieu = '' WHERE lieu IS NULL");
                $this->addSql('ALTER TABLE formation MODIFY lieu VARCHAR(255) NOT NULL');
            } elseif ($platform instanceof PostgreSQLPlatform) {
                $this->addSql("UPDATE formation SET lieu = '' WHERE lieu IS NULL");
                $this->addSql('ALTER TABLE formation ALTER COLUMN lieu SET NOT NULL');
                $this->addSql('ALTER TABLE formation ALTER COLUMN lieu TYPE VARCHAR(255)');
            }
        }
    }
}
