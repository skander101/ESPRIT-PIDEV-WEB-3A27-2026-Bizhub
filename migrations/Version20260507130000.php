<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Sync database schema to match entity mapping.
 */
final class Version20260507130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sync DB schema: column types, FKs, and indexes to match mapping';
    }

    public function up(Schema $schema): void
    {
        // ========== orders table ==========
        $this->addSql('ALTER TABLE orders DROP FOREIGN KEY `FK_orders_product`');
        $this->addSql('ALTER TABLE orders DROP FOREIGN KEY `FK_orders_buyer`');
        $this->addSql('DROP INDEX IDX_E52FFDEE4584665A ON orders');
        $this->addSql('DROP INDEX IDX_E52FFDEE6C755722 ON orders');
        $this->addSql('ALTER TABLE orders CHANGE order_date order_date DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT `FK_orders_product` FOREIGN KEY (product_id) REFERENCES product_service (product_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT `FK_orders_buyer` FOREIGN KEY (buyer_id) REFERENCES app_user (user_id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_orders_product ON orders (product_id)');
        $this->addSql('CREATE INDEX IDX_orders_buyer ON orders (buyer_id)');

        // ========== commande_status_history ==========
        // FK_EA31081482EA2E54 already exists

        // ========== produit_service (typo table) ==========
        $this->addSql('ALTER TABLE produit_service CHANGE description description LONGTEXT DEFAULT NULL, CHANGE quantite quantite INT NOT NULL, CHANGE owner_user_id owner_user_id INT DEFAULT NULL');

        // ========== product_service table ==========
        $this->addSql('ALTER TABLE product_service DROP FOREIGN KEY `product_service_ibfk_1`');
        $this->addSql('DROP INDEX IDX_304481628DE820D9 ON product_service');
        $this->addSql('DROP INDEX idx_product_fournisseur ON product_service');
        $this->addSql('ALTER TABLE product_service CHANGE description description LONGTEXT DEFAULT NULL, CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE product_service ADD CONSTRAINT `product_service_ibfk_1` FOREIGN KEY (seller_id) REFERENCES app_user (user_id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX idx_product_service_seller ON product_service (seller_id)');
        $this->addSql('CREATE INDEX idx_product_category ON product_service (category)');
        $this->addSql('CREATE INDEX idx_product_price ON product_service (price)');
        $this->addSql('CREATE INDEX idx_product_stock ON product_service (stock)');

        // ========== payment table ==========
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY `payment_ibfk_1`');
        $this->addSql('DROP INDEX IDX_6D28840D6E1B4FD5 ON payment');
        $this->addSql('ALTER TABLE payment CHANGE payment_date payment_date DATETIME NOT NULL, CHANGE payment_method payment_method VARCHAR(255) NOT NULL, CHANGE payment_status payment_status VARCHAR(255) NOT NULL, CHANGE notes notes LONGTEXT NOT NULL');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT `payment_ibfk_1` FOREIGN KEY (investment_id) REFERENCES investment (investment_id)');
        $this->addSql('CREATE INDEX IDX_payment_investment ON payment (investment_id)');

        // ========== participation table ==========
        $this->addSql('ALTER TABLE participation DROP FOREIGN KEY `participation_ibfk_1`');
        $this->addSql('ALTER TABLE participation DROP FOREIGN KEY `participation_ibfk_2`');
        $this->addSql('DROP INDEX IDX_AB55E24FA76ED395 ON participation');
        $this->addSql('DROP INDEX IDX_AB55E24F5200282E ON participation');
        $this->addSql('ALTER TABLE participation CHANGE user_id user_id INT DEFAULT NULL, CHANGE date_affectation date_affectation DATETIME DEFAULT NULL, CHANGE remarques remarques LONGTEXT DEFAULT NULL, CHANGE formation_id formation_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE participation ADD CONSTRAINT `participation_ibfk_1` FOREIGN KEY (user_id) REFERENCES app_user (user_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE participation ADD CONSTRAINT `participation_ibfk_2` FOREIGN KEY (formation_id) REFERENCES formation (formation_id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX idx_participation_user ON participation (user_id)');
        $this->addSql('CREATE INDEX idx_participation_formation ON participation (formation_id)');

        // ========== promo_code table ==========
        $this->addSql('ALTER TABLE promo_code DROP FOREIGN KEY `FK_PROMO_PARTICIPATION_SOURCE`');
        $this->addSql('DROP INDEX IDX_3D8C939EFBBB320A ON promo_code');
        $this->addSql('ALTER TABLE promo_code CHANGE created_at created_at DATETIME NOT NULL, CHANGE expires_at expires_at DATETIME NOT NULL, CHANGE used_at used_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE promo_code ADD CONSTRAINT `FK_PROMO_PARTICIPATION_SOURCE` FOREIGN KEY (participation_source_id) REFERENCES participation (id_candidature) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_promo_participation_source ON promo_code (participation_source_id)');
        $this->addSql('CREATE INDEX idx_promo_code_user ON promo_code (user_id)');

        // ========== training_request table ==========
        $this->addSql('ALTER TABLE training_request DROP FOREIGN KEY `training_request_ibfk_1`');
        $this->addSql('ALTER TABLE training_request DROP FOREIGN KEY `training_request_ibfk_2`');
        $this->addSql('DROP INDEX IDX_E6A91F9167B339C5 ON training_request');
        $this->addSql('DROP INDEX IDX_E6A91F915200282E ON training_request');
        $this->addSql('ALTER TABLE training_request CHANGE request_date request_date DATETIME NOT NULL, CHANGE status status VARCHAR(50) DEFAULT \'pending\' NOT NULL');
        $this->addSql('ALTER TABLE training_request ADD CONSTRAINT `training_request_ibfk_1` FOREIGN KEY (startup_id) REFERENCES app_user (user_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE training_request ADD CONSTRAINT `training_request_ibfk_2` FOREIGN KEY (formation_id) REFERENCES formation (formation_id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_training_request_startup ON training_request (startup_id)');
        $this->addSql('CREATE INDEX IDX_training_request_formation ON training_request (formation_id)');
        $this->addSql('CREATE INDEX idx_training_request_status ON training_request (status)');

        // ========== formation table ==========
        $this->addSql('ALTER TABLE formation DROP FOREIGN KEY `formation_ibfk_1`');
        $this->addSql('DROP INDEX IDX_404021BFFB08EDF6 ON formation');
        $this->addSql('ALTER TABLE formation ADD CONSTRAINT `formation_ibfk_1` FOREIGN KEY (trainer_id) REFERENCES app_user (user_id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX idx_formation_trainer ON formation (trainer_id)');

        // ========== negotiation table ==========
        $this->addSql('ALTER TABLE negotiation DROP FOREIGN KEY `negotiation_ibfk_1`');
        $this->addSql('ALTER TABLE negotiation DROP FOREIGN KEY `negotiation_ibfk_2`');
        $this->addSql('ALTER TABLE negotiation DROP FOREIGN KEY `negotiation_ibfk_3`');
        $this->addSql('DROP INDEX IDX_17989598166D1F9C ON negotiation');
        $this->addSql('DROP INDEX IDX_1798959867B339C5 ON negotiation');
        $this->addSql('DROP INDEX IDX_179895989AE528DA ON negotiation');
        $this->addSql('ALTER TABLE negotiation ADD CONSTRAINT `negotiation_ibfk_1` FOREIGN KEY (project_id) REFERENCES project (project_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE negotiation ADD CONSTRAINT `negotiation_ibfk_2` FOREIGN KEY (startup_id) REFERENCES app_user (user_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE negotiation ADD CONSTRAINT `negotiation_ibfk_3` FOREIGN KEY (investor_id) REFERENCES app_user (user_id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_negotiation_project ON negotiation (project_id)');
        $this->addSql('CREATE INDEX IDX_negotiation_startup ON negotiation (startup_id)');
        $this->addSql('CREATE INDEX IDX_negotiation_investor ON negotiation (investor_id)');

        // ========== negotiation_message table ==========
        $this->addSql('ALTER TABLE negotiation_message DROP FOREIGN KEY `negotiation_message_ibfk_1`');
        $this->addSql('ALTER TABLE negotiation_message DROP FOREIGN KEY `negotiation_message_ibfk_2`');
        $this->addSql('DROP INDEX IDX_A095F86167A34946 ON negotiation_message');
        $this->addSql('DROP INDEX IDX_A095F861F624B39D ON negotiation_message');
        $this->addSql('ALTER TABLE negotiation_message CHANGE message message LONGTEXT NOT NULL, CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE negotiation_message ADD CONSTRAINT `negotiation_message_ibfk_1` FOREIGN KEY (negotiation_id) REFERENCES negotiation (negotiation_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE negotiation_message ADD CONSTRAINT `negotiation_message_ibfk_2` FOREIGN KEY (sender_id) REFERENCES app_user (user_id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_negotiation_message_negotiation ON negotiation_message (negotiation_id)');
        $this->addSql('CREATE INDEX IDX_negotiation_message_sender ON negotiation_message (sender_id)');

        // ========== project table ==========
        $this->addSql('ALTER TABLE project DROP FOREIGN KEY `project_ibfk_1`');
        $this->addSql('DROP INDEX IDX_2FB3D0EE67B339C5 ON project');
        $this->addSql('DROP INDEX idx_project_status ON project');
        $this->addSql('DROP INDEX idx_project_budget ON project');
        $this->addSql('ALTER TABLE project CHANGE description description LONGTEXT DEFAULT NULL, CHANGE problem_description problem_description LONGTEXT DEFAULT NULL, CHANGE solution_description solution_description LONGTEXT DEFAULT NULL, CHANGE competitive_advantage competitive_advantage LONGTEXT DEFAULT NULL, CHANGE created_at created_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE project ADD CONSTRAINT `project_ibfk_1` FOREIGN KEY (startup_id) REFERENCES app_user (user_id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX idx_project_startup ON project (startup_id)');
        $this->addSql('CREATE INDEX idx_project_status ON project (status)');
        $this->addSql('CREATE INDEX idx_project_budget ON project (required_budget)');

        // ========== deal table ==========
        $this->addSql('ALTER TABLE deal DROP FOREIGN KEY `FK_E3FEC116166D1F9C`');
        $this->addSql('ALTER TABLE deal DROP FOREIGN KEY `FK_E3FEC11667A34946`');
        $this->addSql('ALTER TABLE deal DROP FOREIGN KEY `FK_E3FEC1166C755722`');
        $this->addSql('ALTER TABLE deal DROP FOREIGN KEY `FK_E3FEC1168DE820D9`');
        $this->addSql('DROP INDEX IDX_E3FEC11667A34946 ON deal');
        $this->addSql('DROP INDEX IDX_E3FEC116166D1F9C ON deal');
        $this->addSql('DROP INDEX IDX_E3FEC1166C755722 ON deal');
        $this->addSql('DROP INDEX IDX_E3FEC1168DE820D9 ON deal');
        $this->addSql('ALTER TABLE deal ADD CONSTRAINT `deal_ibfk_1` FOREIGN KEY (project_id) REFERENCES project (project_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE deal ADD CONSTRAINT `deal_ibfk_2` FOREIGN KEY (negotiation_id) REFERENCES negotiation (negotiation_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE deal ADD CONSTRAINT `deal_ibfk_3` FOREIGN KEY (buyer_id) REFERENCES app_user (user_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE deal ADD CONSTRAINT `deal_ibfk_4` FOREIGN KEY (seller_id) REFERENCES app_user (user_id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_deal_project ON deal (project_id)');
        $this->addSql('CREATE INDEX IDX_deal_negotiation ON deal (negotiation_id)');
        $this->addSql('CREATE INDEX IDX_deal_buyer ON deal (buyer_id)');
        $this->addSql('CREATE INDEX IDX_deal_seller ON deal (seller_id)');

        // ========== avis table ==========
        $this->addSql('ALTER TABLE avis DROP FOREIGN KEY `FK_8F91ABF05200282E`');
        $this->addSql('ALTER TABLE avis DROP FOREIGN KEY `FK_8F91ABF070574616`');
        $this->addSql('DROP INDEX IDX_8F91ABF05200282E ON avis');
        $this->addSql('DROP INDEX IDX_8F91ABF070574616 ON avis');
        $this->addSql('ALTER TABLE avis ADD CONSTRAINT `avis_ibfk_1` FOREIGN KEY (formation_id) REFERENCES formation (formation_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE avis ADD CONSTRAINT `avis_ibfk_2` FOREIGN KEY (reviewer_id) REFERENCES app_user (user_id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX idx_avis_formation ON avis (formation_id)');
        $this->addSql('CREATE INDEX idx_avis_reviewer ON avis (reviewer_id)');

        // ========== commentaire table ==========
        $this->addSql('ALTER TABLE commentaire DROP FOREIGN KEY `FK_67F068BC4B89032C`');
        $this->addSql('ALTER TABLE commentaire DROP FOREIGN KEY `FK_67F068BCA76ED395`');
        $this->addSql('DROP INDEX IDX_67F068BC4B89032C ON commentaire');
        $this->addSql('DROP INDEX IDX_67F068BCA76ED395 ON commentaire');
        $this->addSql('ALTER TABLE commentaire ADD CONSTRAINT `commentaire_ibfk_1` FOREIGN KEY (post_id) REFERENCES post (post_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE commentaire ADD CONSTRAINT `commentaire_ibfk_2` FOREIGN KEY (user_id) REFERENCES app_user (user_id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX idx_commentaire_post ON commentaire (post_id)');
        $this->addSql('CREATE INDEX idx_commentaire_user ON commentaire (user_id)');

        // ========== post table ==========
        $this->addSql('ALTER TABLE post CHANGE user_id user_id INT DEFAULT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE media_type media_type VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE post ADD CONSTRAINT `post_ibfk_1` FOREIGN KEY (user_id) REFERENCES app_user (user_id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX idx_post_user ON post (user_id)');

        // ========== reaction table ==========
        $this->addSql('ALTER TABLE reaction CHANGE post_id post_id INT DEFAULT NULL, CHANGE user_id user_id INT DEFAULT NULL, CHANGE type type VARCHAR(50) NOT NULL, CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE reaction ADD CONSTRAINT `reaction_ibfk_1` FOREIGN KEY (post_id) REFERENCES post (post_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reaction ADD CONSTRAINT `reaction_ibfk_2` FOREIGN KEY (user_id) REFERENCES app_user (user_id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_reaction_post ON reaction (post_id)');
        $this->addSql('CREATE INDEX IDX_reaction_user ON reaction (user_id)');
    }

    public function down(Schema $schema): void
    {
        // Simplified down - restore some old FKs
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
        $this->addSql('ALTER TABLE promo_code DROP FOREIGN KEY `FK_PROMO_PARTICIPATION_SOURCE`');
        $this->addSql('ALTER TABLE participation DROP FOREIGN KEY `participation_ibfk_2`');
        $this->addSql('ALTER TABLE participation DROP FOREIGN KEY `participation_ibfk_1`');
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY `payment_ibfk_1`');
        $this->addSql('ALTER TABLE product_service DROP FOREIGN KEY `product_service_ibfk_1`');
        $this->addSql('ALTER TABLE orders DROP FOREIGN KEY `FK_orders_buyer`');
        $this->addSql('ALTER TABLE orders DROP FOREIGN KEY `FK_orders_product`');
        $this->addSql('ALTER TABLE commande_status_history DROP FOREIGN KEY FK_EA31081482EA2E54');
    }
}
