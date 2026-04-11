<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260410123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow multiple avis per formation (one per user+formation).';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        if (!$sm->tablesExist(['avis'])) {
            return;
        }

        $platform = $this->connection->getDatabasePlatform();
        $indexes = $sm->listTableIndexes('avis');

        foreach ($indexes as $index) {
            $columns = array_map('strtolower', $index->getColumns());
            if ($index->isUnique() && $columns === ['formation_id']) {
                if ($platform instanceof AbstractMySQLPlatform) {
                    $this->addSql(sprintf('ALTER TABLE avis DROP INDEX %s', $index->getName()));
                } elseif ($platform instanceof PostgreSQLPlatform) {
                    $this->addSql(sprintf('DROP INDEX IF EXISTS %s', $index->getName()));
                }
            }
        }

        $hasCompositeUnique = false;
        foreach ($indexes as $index) {
            $columns = array_map('strtolower', $index->getColumns());
            if ($index->isUnique() && $columns === ['reviewer_id', 'formation_id']) {
                $hasCompositeUnique = true;
                break;
            }
        }

        if (!$hasCompositeUnique) {
            if ($platform instanceof AbstractMySQLPlatform) {
                $this->addSql('ALTER TABLE avis ADD UNIQUE INDEX unique_user_formation (reviewer_id, formation_id)');
            } elseif ($platform instanceof PostgreSQLPlatform) {
                $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS unique_user_formation ON avis (reviewer_id, formation_id)');
            }
        }
    }

    public function down(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        if (!$sm->tablesExist(['avis'])) {
            return;
        }

        $platform = $this->connection->getDatabasePlatform();
        $indexes = $sm->listTableIndexes('avis');

        foreach ($indexes as $index) {
            $columns = array_map('strtolower', $index->getColumns());
            if ($index->isUnique() && $columns === ['reviewer_id', 'formation_id']) {
                if ($platform instanceof AbstractMySQLPlatform) {
                    $this->addSql(sprintf('ALTER TABLE avis DROP INDEX %s', $index->getName()));
                } elseif ($platform instanceof PostgreSQLPlatform) {
                    $this->addSql(sprintf('DROP INDEX IF EXISTS %s', $index->getName()));
                }
                break;
            }
        }

        $hasSingleFormationUnique = false;
        $indexes = $sm->listTableIndexes('avis');
        foreach ($indexes as $index) {
            $columns = array_map('strtolower', $index->getColumns());
            if ($index->isUnique() && $columns === ['formation_id']) {
                $hasSingleFormationUnique = true;
                break;
            }
        }

        if (!$hasSingleFormationUnique) {
            if ($platform instanceof AbstractMySQLPlatform) {
                $this->addSql('ALTER TABLE avis ADD UNIQUE INDEX uniq_avis_formation (formation_id)');
            } elseif ($platform instanceof PostgreSQLPlatform) {
                $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_avis_formation ON avis (formation_id)');
            }
        }
    }
}
