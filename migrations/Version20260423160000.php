<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260423160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Formation recommendation tracking events (impressions, clicks, enrolls).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE formation_recommendation_event (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT DEFAULT NULL,
            formation_id INT NOT NULL,
            section VARCHAR(32) NOT NULL,
            event_type VARCHAR(24) NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX idx_fre_formation (formation_id),
            INDEX idx_fre_user_created (user_id, created_at),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE formation_recommendation_event ADD CONSTRAINT FK_FRE_USER FOREIGN KEY (user_id) REFERENCES user (user_id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE formation_recommendation_event ADD CONSTRAINT FK_FRE_FORMATION FOREIGN KEY (formation_id) REFERENCES formation (formation_id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE formation_recommendation_event DROP FOREIGN KEY FK_FRE_USER');
        $this->addSql('ALTER TABLE formation_recommendation_event DROP FOREIGN KEY FK_FRE_FORMATION');
        $this->addSql('DROP TABLE formation_recommendation_event');
    }
}
