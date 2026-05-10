<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Final schema sync: drop old FKs, add new ones with proper names.
 */
final class Version20260507150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sync DB schema with entity mapping (FK names, column types, indexes)';
    }

    public function up(Schema $schema): void
    {
        // ========= orders table =========
        // Drop existing FKs (they use older naming)
        $this->addSql('ALTER TABLE orders DROP FOREIGN KEY FK_E52FFDEE6C755722');
        $this->addSql('ALTER TABLE orders DROP FOREIGN KEY FK_E52FFDEE4584665A');
        // Add FKs with proper backtick names
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT `FK_orders_buyer` FOREIGN KEY (buyer_id) REFERENCES app_user (user_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT `FK_orders_product` FOREIGN KEY (product_id) REFERENCES product_service (product_id) ON DELETE CASCADE');
        // Fix column type
        $this->addSql('ALTER TABLE orders CHANGE order_date order_date DATETIME DEFAULT NULL');

        // ========= product_service table =========
        // Drop existing FK
        $this->addSql('ALTER TABLE product_service DROP FOREIGN KEY FK_304481628DE820D9');
        // Add FK with proper name
        $this->addSql('ALTER TABLE product_service ADD CONSTRAINT `product_service_ibfk_1` FOREIGN KEY (seller_id) REFERENCES app_user (user_id) ON DELETE CASCADE');
        // Fix column types
        $this->addSql('ALTER TABLE product_service CHANGE description description LONGTEXT DEFAULT NULL, CHANGE created_at created_at DATETIME NOT NULL');

        // ========= payment table =========
        // Drop existing FK
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840D6E1B4FD5');
        // Add FK with proper name
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT `payment_ibfk_1` FOREIGN KEY (investment_id) REFERENCES investment (investment_id)');
        // Fix column types
        $this->addSql('ALTER TABLE payment CHANGE payment_date payment_date DATETIME NOT NULL, CHANGE payment_method payment_method VARCHAR(255) NOT NULL, CHANGE payment_status payment_status VARCHAR(255) NOT NULL, CHANGE notes notes LONGTEXT NOT NULL');

        // ========= participation table =========
        // Drop existing FKs
        $this->addSql('ALTER TABLE participation DROP FOREIGN KEY FK_AB55E24FA76ED395');
        $this->addSql('ALTER TABLE participation DROP FOREIGN KEY FK_AB55E24F5200282E');
        // Add FKs with proper names
        $this->addSql('ALTER TABLE participation ADD CONSTRAINT `participation_ibfk_1` FOREIGN KEY (user_id) REFERENCES app_user (user_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE participation ADD CONSTRAINT `participation_ibfk_2` FOREIGN KEY (formation_id) REFERENCES formation (formation_id) ON DELETE CASCADE');
        // Fix column types
        $this->addSql('ALTER TABLE participation CHANGE user_id user_id INT DEFAULT NULL, CHANGE date_affectation date_affectation DATETIME DEFAULT NULL, CHANGE remarques remarques LONGTEXT DEFAULT NULL, CHANGE formation_id formation_id INT DEFAULT NULL');

        // ========= promo_code table =========
        // Drop existing FK
        $this->addSql('ALTER TABLE promo_code DROP FOREIGN KEY FK_3D8C939EFBBB320A');
        // Add FK with proper name
        $this->addSql('ALTER TABLE promo_code ADD CONSTRAINT `promo_code_ibfk_1` FOREIGN KEY (participation_source_id) REFERENCES participation (id_candidature) ON DELETE SET NULL');
        // Fix column types
        $this->addSql('ALTER TABLE promo_code CHANGE created_at created_at DATETIME NOT NULL, CHANGE expires_at expires_at DATETIME NOT NULL, CHANGE used_at used_at DATETIME DEFAULT NULL');

        // ========= training_request table =========
        // Drop existing FKs
        $this->addSql('ALTER TABLE training_request DROP FOREIGN KEY FK_E6A91F9167B339C5');
        $this->addSql('ALTER TABLE training_request DROP FOREIGN KEY FK_E6A91F915200282E');
        // Add FKs with proper names
        $this->addSql('ALTER TABLE training_request ADD CONSTRAINT `training_request_ibfk_1` FOREIGN KEY (startup_id) REFERENCES app_user (user_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE training_request ADD CONSTRAINT `training_request_ibfk_2` FOREIGN KEY (formation_id) REFERENCES formation (formation_id) ON DELETE CASCADE');
        // Fix column types
        $this->addSql('ALTER TABLE training_request CHANGE request_date request_date DATETIME NOT NULL, CHANGE status status VARCHAR(50) DEFAULT \'pending\' NOT NULL');

        // ========= formation table =========
        // Drop existing FK
        $this->addSql('ALTER TABLE formation DROP FOREIGN KEY FK_404021BFFB08EDF6');
        // Add FK with proper name
        $this->addSql('ALTER TABLE formation ADD CONSTRAINT `formation_ibfk_1` FOREIGN KEY (trainer_id) REFERENCES app_user (user_id) ON DELETE SET NULL');

        // ========= negotiation table =========
        // Drop existing FKs
        $this->addSql('ALTER TABLE negotiation DROP FOREIGN KEY FK_17989598166D1F9C');
        $this->addSql('ALTER TABLE negotiation DROP FOREIGN KEY FK_1798959867B339C5');
        $this->addSql('ALTER TABLE negotiation DROP FOREIGN KEY FK_179895989AE528DA');
        // Add FKs with proper names
        $this->addSql('ALTER TABLE negotiation ADD CONSTRAINT `negotiation_ibfk_1` FOREIGN KEY (project_id) REFERENCES project (project_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE negotiation ADD CONSTRAINT `negotiation_ibfk_2` FOREIGN KEY (startup_id) REFERENCES app_user (user_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE negotiation ADD CONSTRAINT `negotiation_ibfk_3` FOREIGN KEY (investor_id) REFERENCES app_user (user_id) ON DELETE CASCADE');

        // ========= negotiation_message table =========
        // Drop existing FKs
        $this->addSql('ALTER TABLE negotiation_message DROP FOREIGN KEY FK_A095F86167A34946');
        $this->addSql('ALTER TABLE negotiation_message DROP FOREIGN KEY FK_A095F861F624B39D');
        // Add FKs with proper names
        $this->addSql('ALTER TABLE negotiation_message ADD CONSTRAINT `negotiation_message_ibfk_1` FOREIGN KEY (negotiation_id) REFERENCES negotiation (negotiation_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE negotiation_message ADD CONSTRAINT `negotiation_message_ibfk_2` FOREIGN KEY (sender_id) REFERENCES app_user (user_id) ON DELETE CASCADE');
        // Fix column types
        $this->addSql('ALTER TABLE negotiation_message CHANGE message message LONGTEXT NOT NULL, CHANGE created_at created_at DATETIME NOT NULL');

        // ========= project table =========
        // Drop existing FK
        $this->addSql('ALTER TABLE project DROP FOREIGN KEY FK_2FB3D0EE67B339C5');
        // Add FK with proper name
        $this->addSql('ALTER TABLE project ADD CONSTRAINT `project_ibfk_1` FOREIGN KEY (startup_id) REFERENCES app_user (user_id) ON DELETE CASCADE');
        // Fix column types
        $this->addSql('ALTER TABLE project CHANGE description description LONGTEXT DEFAULT NULL, CHANGE problem_description problem_description LONGTEXT DEFAULT NULL, CHANGE solution_description solution_description LONGTEXT DEFAULT NULL, CHANGE competitive_advantage competitive_advantage LONGTEXT DEFAULT NULL, CHANGE created_at created_at DATETIME DEFAULT NULL');

        // ========= deal table =========
        // Drop existing FKs
        $this->addSql('ALTER TABLE deal DROP FOREIGN KEY FK_E3FEC116166D1F9C');
        $this->addSql('ALTER TABLE deal DROP FOREIGN KEY FK_E3FEC11667A34946');
        $this->addSql('ALTER TABLE deal DROP FOREIGN KEY FK_E3FEC1166C755722');
        $this->addSql('ALTER TABLE deal DROP FOREIGN KEY FK_E3FEC1168DE820D9');
        // Add FKs with proper names
        $this->addSql('ALTER TABLE deal ADD CONSTRAINT `deal_ibfk_1` FOREIGN KEY (project_id) REFERENCES project (project_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE deal ADD CONSTRAINT `deal_ibfk_2` FOREIGN KEY (negotiation_id) REFERENCES negotiation (negotiation_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE deal ADD CONSTRAINT `deal_ibfk_3` FOREIGN KEY (buyer_id) REFERENCES app_user (user_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE deal ADD CONSTRAINT `deal_ibfk_4` FOREIGN KEY (seller_id) REFERENCES app_user (user_id) ON DELETE CASCADE');

        // ========= avis table =========
        // Drop existing FKs
        $this->addSql('ALTER TABLE avis DROP FOREIGN KEY FK_8F91ABF05200282E');
        $this->addSql('ALTER TABLE avis DROP FOREIGN KEY FK_8F91ABF070574616');
        // Add FKs with proper names
        $this->addSql('ALTER TABLE avis ADD CONSTRAINT `avis_ibfk_1` FOREIGN KEY (formation_id) REFERENCES formation (formation_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE avis ADD CONSTRAINT `avis_ibfk_2` FOREIGN KEY (reviewer_id) REFERENCES app_user (user_id) ON DELETE CASCADE');

        // ========= commentaire table =========
        // Drop existing FKs
        $this->addSql('ALTER TABLE commentaire DROP FOREIGN KEY FK_67F068BC4B89032C');
        $this->addSql('ALTER TABLE commentaire DROP FOREIGN KEY FK_67F068BCA76ED395');
        // Add FKs with proper names
        $this->addSql('ALTER TABLE commentaire ADD CONSTRAINT `commentaire_ibfk_1` FOREIGN KEY (post_id) REFERENCES post (post_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE commentaire ADD CONSTRAINT `commentaire_ibfk_2` FOREIGN KEY (user_id) REFERENCES app_user (user_id) ON DELETE CASCADE');

        // ========= post table =========
        // Fix column types
        $this->addSql('ALTER TABLE post CHANGE user_id user_id INT DEFAULT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE media_type media_type VARCHAR(50) DEFAULT NULL');
        // Add FK
        $this->addSql('ALTER TABLE post ADD CONSTRAINT `post_ibfk_1` FOREIGN KEY (user_id) REFERENCES app_user (user_id) ON DELETE SET NULL');

        // ========= reaction table =========
        // Fix column types
        $this->addSql('ALTER TABLE reaction CHANGE post_id post_id INT DEFAULT NULL, CHANGE user_id user_id INT DEFAULT NULL, CHANGE type type VARCHAR(50) NOT NULL, CHANGE created_at created_at DATETIME NOT NULL');
        // Add FKs
        $this->addSql('ALTER TABLE reaction ADD CONSTRAINT `reaction_ibfk_1` FOREIGN KEY (post_id) REFERENCES post (post_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reaction ADD CONSTRAINT `reaction_ibfk_2` FOREIGN KEY (user_id) REFERENCES app_user (user_id) ON DELETE CASCADE');

        // ========= produit_service table (typo table) =========
        // Fix column types
        $this->addSql('ALTER TABLE produit_service CHANGE description description LONGTEXT DEFAULT NULL, CHANGE quantite quantite INT NOT NULL, CHANGE owner_user_id owner_user_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // Down migration - simplified
        $this->addSql('ALTER TABLE reaction DROP FOREIGN KEY `reaction_ibfk_2`');
        $this->addSql('ALTER TABLE reaction DROP FOREIGN KEY `reaction_ibfk_1`');
        $this->addSql('ALTER TABLE post DROP FOREIGN KEY `post_ibfk_1`');
        $this->addSql('ALTER TABLE commentaire DROP FOREIGN KEY `commentaire_ibfk_2`');
        $this->addSql('ALTER TABLE commentaire DROP FOREIGN KEY `commentaire_ibfk_1`');
        $this->addSql('ALTER TABLE avis DROP FOREIGN KEY `avis_ibfk_2`');
        $this->addSql('ALTER TABLE avis DROP FOREIGN KEY `avis_ibfk_1`');
        $this->addSql('ALTER TABLE deal DROP FOREIGN KEY `deal_ibfk_4`');
        $this->addSql('ALTER TABLE deal DROP FOREIGN KEY `deal_ibfk_3`');
        $this->addSql('ALTER TABLE deal DROP FOREIGN KEY `deal_ibfk_2`');
        $this->addSql('ALTER TABLE deal DROP FOREIGN KEY `deal_ibfk_1`');
        $this->addSql('ALTER TABLE project DROP FOREIGN KEY `project_ibfk_1`');
        $this->addSql('ALTER TABLE negotiation_message DROP FOREIGN KEY `negotiation_message_ibfk_2`');
        $this->addSql('ALTER TABLE negotiation_message DROP FOREIGN KEY `negotiation_message_ibfk_1`');
        $this->addSql('ALTER TABLE negotiation DROP FOREIGN KEY `negotiation_ibfk_3`');
        $this->addSql('ALTER TABLE negotiation DROP FOREIGN KEY `negotiation_ibfk_2`');
        $this->addSql('ALTER TABLE negotiation DROP FOREIGN KEY `negotiation_ibfk_1`');
        $this->addSql('ALTER TABLE formation DROP FOREIGN KEY `formation_ibfk_1`');
        $this->addSql('ALTER TABLE training_request DROP FOREIGN KEY `training_request_ibfk_2`');
        $this->addSql('ALTER TABLE training_request DROP FOREIGN KEY `training_request_ibfk_1`');
        $this->addSql('ALTER TABLE promo_code DROP FOREIGN KEY `promo_code_ibfk_1`');
        $this->addSql('ALTER TABLE participation DROP FOREIGN KEY `participation_ibfk_2`');
        $this->addSql('ALTER TABLE participation DROP FOREIGN KEY `participation_ibfk_1`');
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY `payment_ibfk_1`');
        $this->addSql('ALTER TABLE product_service DROP FOREIGN KEY `product_service_ibfk_1`');
        $this->addSql('ALTER TABLE orders DROP FOREIGN KEY `FK_orders_product`');
        $this->addSql('ALTER TABLE orders DROP FOREIGN KEY `FK_orders_buyer`');
    }
}
