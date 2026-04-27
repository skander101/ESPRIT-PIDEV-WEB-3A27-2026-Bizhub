<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260410140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add formation.max_formateurs and ensure unique training_request (user_id, formation_id).';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        $platform = $this->connection->getDatabasePlatform();

        if ($sm->tablesExist(['formation'])) {
            $columns = $sm->listTableColumns('formation');
            $names = array_map(static fn (string $k): string => strtolower($k), array_keys($columns));
            if (!in_array('max_formateurs', $names, true)) {
                if ($platform instanceof AbstractMySQLPlatform) {
                    $this->addSql('ALTER TABLE formation ADD max_formateurs INT NOT NULL DEFAULT 1');
                } elseif ($platform instanceof PostgreSQLPlatform) {
                    $this->addSql('ALTER TABLE formation ADD max_formateurs INT NOT NULL DEFAULT 1');
                }
            }
        }

        if ($sm->tablesExist(['training_request'])) {
            $columns = $sm->listTableColumns('training_request');
            $columnNames = array_map(static fn (string $k): string => strtolower($k), array_keys($columns));
            $actorColumn = in_array('startup_id', $columnNames, true) ? 'startup_id' : 'user_id';
            $indexes = $sm->listTableIndexes('training_request');
            $hasUniqueUserFormation = false;
            foreach ($indexes as $index) {
                $cols = array_map('strtolower', $index->getColumns());
                if ($index->isUnique() && $cols === [$actorColumn, 'formation_id']) {
                    $hasUniqueUserFormation = true;
                    break;
                }
            }

            if (!$hasUniqueUserFormation) {
                if ($platform instanceof AbstractMySQLPlatform) {
                    $this->addSql(sprintf('ALTER TABLE training_request ADD UNIQUE INDEX uq_training_request_user_formation (%s, formation_id)', $actorColumn));
                } elseif ($platform instanceof PostgreSQLPlatform) {
                    $this->addSql(sprintf('CREATE UNIQUE INDEX IF NOT EXISTS uq_training_request_user_formation ON training_request (%s, formation_id)', $actorColumn));
                }
            }
        }
    }

    public function down(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        $platform = $this->connection->getDatabasePlatform();

        if ($sm->tablesExist(['training_request'])) {
            $columns = $sm->listTableColumns('training_request');
            $columnNames = array_map(static fn (string $k): string => strtolower($k), array_keys($columns));
            $actorColumn = in_array('startup_id', $columnNames, true) ? 'startup_id' : 'user_id';
            $indexes = $sm->listTableIndexes('training_request');
            foreach ($indexes as $index) {
                $cols = array_map('strtolower', $index->getColumns());
                if ($index->isUnique() && $cols === [$actorColumn, 'formation_id']) {
                    if ($platform instanceof AbstractMySQLPlatform) {
                        $this->addSql(sprintf('ALTER TABLE training_request DROP INDEX %s', $index->getName()));
                    } elseif ($platform instanceof PostgreSQLPlatform) {
                        $this->addSql(sprintf('DROP INDEX IF EXISTS %s', $index->getName()));
                    }
                    break;
                }
            }
        }

        if ($sm->tablesExist(['formation'])) {
            $columns = $sm->listTableColumns('formation');
            $names = array_map(static fn (string $k): string => strtolower($k), array_keys($columns));
            if (in_array('max_formateurs', $names, true)) {
                $this->addSql('ALTER TABLE formation DROP COLUMN max_formateurs');
            }
        }
    }
}
