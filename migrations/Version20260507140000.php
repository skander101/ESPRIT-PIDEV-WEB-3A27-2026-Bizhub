<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Final schema sync: fix column types, add missing indexes.
 */
final class Version20260507140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sync DB schema with entity mapping';
    }

    public function up(Schema $schema): void
    {
        // ========= orders table =========
        $this->addSql('ALTER TABLE orders CHANGE order_date order_date DATETIME DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_orders_buyer ON orders (buyer_id)');
        $this->addSql('CREATE INDEX IDX_orders_product ON orders (product_id)');

        // ========= product_service table =========
        $this->addSql('ALTER TABLE product_service CHANGE description description LONGTEXT DEFAULT NULL, CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('CREATE INDEX idx_product_service_seller ON product_service (seller_id)');
        $this->addSql('CREATE INDEX idx_product_category ON product_service (category)');
        $this->addSql('CREATE INDEX idx_product_price ON product_service (price)');
        $this->addSql('CREATE INDEX idx_product_stock ON product_service (stock)');

        // ========= payment table =========
        $this->addSql('ALTER TABLE payment CHANGE payment_date payment_date DATETIME NOT NULL, CHANGE payment_method payment_method VARCHAR(255) NOT NULL, CHANGE payment_status payment_status VARCHAR(255) NOT NULL, CHANGE notes notes LONGTEXT NOT NULL');
        $this->addSql('CREATE INDEX IDX_payment_investment ON payment (investment_id)');

        // ========= participation table =========
        $this->addSql('ALTER TABLE participation CHANGE user_id user_id INT DEFAULT NULL, CHANGE date_affectation date_affectation DATETIME DEFAULT NULL, CHANGE remarques remarques LONGTEXT DEFAULT NULL, CHANGE formation_id formation_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_participation_user ON participation (user_id)');
        $this->addSql('CREATE INDEX idx_participation_formation ON participation (formation_id)');

        // ========= promo_code table =========
        $this->addSql('ALTER TABLE promo_code CHANGE created_at created_at DATETIME NOT NULL, CHANGE expires_at expires_at DATETIME NOT NULL, CHANGE used_at used_at DATETIME DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_promo_participation_source ON promo_code (participation_source_id)');
        $this->addSql('CREATE INDEX idx_promo_code_user ON promo_code (user_id)');

        // ========= training_request table =========
        $this->addSql('ALTER TABLE training_request CHANGE request_date request_date DATETIME NOT NULL, CHANGE status status VARCHAR(50) DEFAULT \'pending\' NOT NULL');
        $this->addSql('CREATE INDEX IDX_training_request_startup ON training_request (startup_id)');
        $this->addSql('CREATE INDEX IDX_training_request_formation ON training_request (formation_id)');
        $this->addSql('CREATE INDEX idx_training_request_status ON training_request (status)');

        // ========= formation table =========
        $this->addSql('CREATE INDEX idx_formation_trainer ON formation (trainer_id)');

        // ========= negotiation table =========
        $this->addSql('CREATE INDEX IDX_negotiation_project ON negotiation (project_id)');
        $this->addSql('CREATE INDEX IDX_negotiation_startup ON negotiation (startup_id)');
        $this->addSql('CREATE INDEX IDX_negotiation_investor ON negotiation (investor_id)');

        // ========= negotiation_message table =========
        $this->addSql('ALTER TABLE negotiation_message CHANGE message message LONGTEXT NOT NULL, CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('CREATE INDEX IDX_negotiation_message_negotiation ON negotiation_message (negotiation_id)');
        $this->addSql('CREATE INDEX IDX_negotiation_message_sender ON negotiation_message (sender_id)');

        // ========= project table =========
        $this->addSql('ALTER TABLE project CHANGE description description LONGTEXT DEFAULT NULL, CHANGE problem_description problem_description LONGTEXT DEFAULT NULL, CHANGE solution_description solution_description LONGTEXT DEFAULT NULL, CHANGE competitive_advantage competitive_advantage LONGTEXT DEFAULT NULL, CHANGE created_at created_at DATETIME DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_project_startup ON project (startup_id)');
        $this->addSql('CREATE INDEX idx_project_status ON project (status)');
        $this->addSql('CREATE INDEX idx_project_budget ON project (required_budget)');

        // ========= deal table =========
        $this->addSql('CREATE INDEX IDX_deal_project ON deal (project_id)');
        $this->addSql('CREATE INDEX IDX_deal_negotiation ON deal (negotiation_id)');
        $this->addSql('CREATE INDEX IDX_deal_buyer ON deal (buyer_id)');
        $this->addSql('CREATE INDEX IDX_deal_seller ON deal (seller_id)');

        // ========= avis table =========
        $this->addSql('CREATE INDEX idx_avis_formation ON avis (formation_id)');
        $this->addSql('CREATE INDEX idx_avis_reviewer ON avis (reviewer_id)');

        // ========= commentaire table =========
        $this->addSql('CREATE INDEX idx_commentaire_post ON commentaire (post_id)');
        $this->addSql('CREATE INDEX idx_commentaire_user ON commentaire (user_id)');

        // ========= post table =========
        $this->addSql('ALTER TABLE post CHANGE created_at created_at DATETIME NOT NULL, CHANGE media_type media_type VARCHAR(50) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_post_user ON post (user_id)');

        // ========= reaction table =========
        $this->addSql('ALTER TABLE reaction CHANGE post_id post_id INT DEFAULT NULL, CHANGE user_id user_id INT DEFAULT NULL, CHANGE type type VARCHAR(50) NOT NULL, CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('CREATE INDEX IDX_reaction_post ON reaction (post_id)');
        $this->addSql('CREATE INDEX IDX_reaction_user ON reaction (user_id)');

        // ========= produit_service table (typo table) =========
        $this->addSql('ALTER TABLE produit_service CHANGE description description LONGTEXT DEFAULT NULL, CHANGE quantite quantite INT NOT NULL, CHANGE owner_user_id owner_user_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // Down migration - simplified
        $this->addSql('DROP INDEX IDX_reaction_user ON reaction');
        $this->addSql('DROP INDEX IDX_reaction_post ON reaction');
        $this->addSql('DROP INDEX idx_post_user ON post');
        $this->addSql('DROP INDEX idx_commentaire_user ON commentaire');
        $this->addSql('DROP INDEX idx_commentaire_post ON commentaire');
        $this->addSql('DROP INDEX idx_avis_reviewer ON avis');
        $this->addSql('DROP INDEX idx_avis_formation ON avis');
        $this->addSql('DROP INDEX IDX_deal_seller ON deal');
        $this->addSql('DROP INDEX IDX_deal_buyer ON deal');
        $this->addSql('DROP INDEX IDX_deal_negotiation ON deal');
        $this->addSql('DROP INDEX IDX_deal_project ON deal');
        $this->addSql('DROP INDEX idx_project_budget ON project');
        $this->addSql('DROP INDEX idx_project_status ON project');
        $this->addSql('DROP INDEX idx_project_startup ON project');
        $this->addSql('DROP INDEX IDX_negotiation_message_sender ON negotiation_message');
        $this->addSql('DROP INDEX IDX_negotiation_message_negotiation ON negotiation_message');
        $this->addSql('DROP INDEX IDX_negotiation_investor ON negotiation');
        $this->addSql('DROP INDEX IDX_negotiation_startup ON negotiation');
        $this->addSql('DROP INDEX IDX_negotiation_project ON negotiation');
        $this->addSql('DROP INDEX idx_formation_trainer ON formation');
        $this->addSql('DROP INDEX idx_training_request_status ON training_request');
        $this->addSql('DROP INDEX IDX_training_request_formation ON training_request');
        $this->addSql('DROP INDEX IDX_training_request_startup ON training_request');
        $this->addSql('DROP INDEX idx_promo_code_user ON promo_code');
        $this->addSql('DROP INDEX IDX_promo_participation_source ON promo_code');
        $this->addSql('DROP INDEX idx_participation_formation ON participation');
        $this->addSql('DROP INDEX idx_participation_user ON participation');
        $this->addSql('DROP INDEX IDX_payment_investment ON payment');
        $this->addSql('DROP INDEX idx_product_stock ON product_service');
        $this->addSql('DROP INDEX idx_product_price ON product_service');
        $this->addSql('DROP INDEX idx_product_category ON product_service');
        $this->addSql('DROP INDEX idx_product_service_seller ON product_service');
        $this->addSql('DROP INDEX IDX_orders_product ON orders');
        $this->addSql('DROP INDEX IDX_orders_buyer ON orders');
    }
}
