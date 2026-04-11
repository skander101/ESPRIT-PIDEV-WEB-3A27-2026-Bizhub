<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260410183000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create user_auth_state table for auth metadata without changing user table schema.';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        if ($sm->tablesExist(['user_auth_state'])) {
            return;
        }

        $this->addSql(<<<'SQL'
CREATE TABLE user_auth_state (
    id INT AUTO_INCREMENT NOT NULL,
    user_id INT NOT NULL,
    is_verified TINYINT(1) NOT NULL DEFAULT 1,
    verification_token VARCHAR(128) DEFAULT NULL,
    verification_token_expires_at DATETIME DEFAULT NULL,
    password_reset_token VARCHAR(128) DEFAULT NULL,
    password_reset_token_expires_at DATETIME DEFAULT NULL,
    mfa_enabled TINYINT(1) NOT NULL DEFAULT 0,
    mfa_enrollment_id VARCHAR(255) DEFAULT NULL,
    oauth_provider VARCHAR(255) DEFAULT NULL,
    oauth_provider_id VARCHAR(255) DEFAULT NULL,
    UNIQUE INDEX uniq_user_auth_state_user (user_id),
    UNIQUE INDEX uniq_user_auth_state_oauth_identity (oauth_provider, oauth_provider_id),
    INDEX idx_user_auth_state_verification_token (verification_token),
    INDEX idx_user_auth_state_password_reset_token (password_reset_token),
    PRIMARY KEY(id),
    CONSTRAINT FK_user_auth_state_user FOREIGN KEY (user_id) REFERENCES user (user_id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        // Seed existing users as verified to keep current accounts usable.
        $this->addSql(<<<'SQL'
INSERT INTO user_auth_state (user_id, is_verified, mfa_enabled)
SELECT u.user_id,
       1,
       CASE WHEN u.totp_secret IS NOT NULL AND u.totp_secret <> '' THEN 1 ELSE 0 END
FROM user u
LEFT JOIN user_auth_state s ON s.user_id = u.user_id
WHERE s.user_id IS NULL
SQL);
    }

    public function down(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        if ($sm->tablesExist(['user_auth_state'])) {
            $this->addSql('DROP TABLE user_auth_state');
        }
    }
}
