<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute totp_secret et face_token sur la table user (schéma aligné avec l'entité User).
 */
final class Version20260408150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add totp_secret and face_token to user table.';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        $columns = $sm->listTableColumns('user');
        $names = array_map(static fn (string $k): string => strtolower($k), array_keys($columns));

        $quotedUser = $this->quoteUserTable();

        if (!in_array('totp_secret', $names, true)) {
            $this->addSql(sprintf(
                'ALTER TABLE %s ADD totp_secret VARCHAR(255) DEFAULT NULL',
                $quotedUser
            ));
        }
        if (!in_array('face_token', $names, true)) {
            $this->addSql(sprintf(
                'ALTER TABLE %s ADD face_token VARCHAR(255) DEFAULT NULL',
                $quotedUser
            ));
        }
    }

    public function down(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        $columns = $sm->listTableColumns('user');
        $names = array_map(static fn (string $k): string => strtolower($k), array_keys($columns));

        $quotedUser = $this->quoteUserTable();

        if (in_array('face_token', $names, true)) {
            $this->addSql(sprintf('ALTER TABLE %s DROP COLUMN face_token', $quotedUser));
        }
        if (in_array('totp_secret', $names, true)) {
            $this->addSql(sprintf('ALTER TABLE %s DROP COLUMN totp_secret', $quotedUser));
        }
    }

    private function quoteUserTable(): string
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof AbstractMySQLPlatform) {
            return '`user`';
        }
        if ($platform instanceof PostgreSQLPlatform) {
            return '"user"';
        }

        return 'user';
    }
}
