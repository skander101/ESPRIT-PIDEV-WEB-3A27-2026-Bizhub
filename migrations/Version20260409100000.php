<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Garantit la colonne booléenne « formation en ligne » pour l'entité Formation.
 * Utilise en_ligne (nom Doctrine habituel). Si ta base a seulement enligne, on renomme.
 */
final class Version20260409100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ensure formation.en_ligne exists (map from enligne or add column).';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        if (!$sm->tablesExist(['formation'])) {
            return;
        }

        $columns = $sm->listTableColumns('formation');
        $names = array_map(static fn (string $k): string => strtolower($k), array_keys($columns));

        if (in_array('en_ligne', $names, true)) {
            return;
        }

        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof AbstractMySQLPlatform) {
            if (in_array('enligne', $names, true)) {
                $this->addSql('ALTER TABLE formation CHANGE enligne en_ligne TINYINT(1) NOT NULL DEFAULT 0');

                return;
            }
            $this->addSql('ALTER TABLE formation ADD en_ligne TINYINT(1) NOT NULL DEFAULT 0');

            return;
        }

        if ($platform instanceof PostgreSQLPlatform) {
            if (in_array('enligne', $names, true)) {
                $this->addSql('ALTER TABLE formation RENAME COLUMN enligne TO en_ligne');

                return;
            }
            $this->addSql('ALTER TABLE formation ADD en_ligne BOOLEAN NOT NULL DEFAULT false');
        }
    }

    public function down(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        if (!$sm->tablesExist(['formation'])) {
            return;
        }

        $columns = $sm->listTableColumns('formation');
        $names = array_map(static fn (string $k): string => strtolower($k), array_keys($columns));

        if (!in_array('en_ligne', $names, true)) {
            return;
        }

        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof AbstractMySQLPlatform) {
            $this->addSql('ALTER TABLE formation CHANGE en_ligne enligne TINYINT(1) NOT NULL DEFAULT 0');

            return;
        }

        if ($platform instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE formation RENAME COLUMN en_ligne TO enligne');
        }
    }
}
