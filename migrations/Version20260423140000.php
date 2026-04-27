<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260423140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'E-learning: promo_code table (post-payment reward, single-use next checkout).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE promo_code (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            code VARCHAR(32) NOT NULL,
            discount_percent INT NOT NULL,
            is_used TINYINT(1) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            used_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            participation_source_id INT DEFAULT NULL,
            UNIQUE INDEX uniq_promo_code (code),
            INDEX idx_promo_code_user (user_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE promo_code ADD CONSTRAINT FK_PROMO_USER FOREIGN KEY (user_id) REFERENCES user (user_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE promo_code ADD CONSTRAINT FK_PROMO_PARTICIPATION_SOURCE FOREIGN KEY (participation_source_id) REFERENCES participation (id_candidature) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE promo_code DROP FOREIGN KEY FK_PROMO_USER');
        $this->addSql('ALTER TABLE promo_code DROP FOREIGN KEY FK_PROMO_PARTICIPATION_SOURCE');
        $this->addSql('DROP TABLE promo_code');
    }
}
