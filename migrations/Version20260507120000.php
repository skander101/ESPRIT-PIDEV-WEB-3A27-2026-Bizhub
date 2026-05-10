<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Sync database schema to match current entity mapping.
 */
final class Version20260507120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sync DB schema to match entity mapping (fix column types, FKs, indexes)';
    }

    public function up(Schema $schema): void
    {
        // panier table
        $this->addSql('DROP INDEX id_produit ON panier');
        $this->addSql('DROP INDEX uq_client_produit ON panier');
        $this->addSql('ALTER TABLE panier CHANGE quantite quantite INT NOT NULL, CHANGE date_ajout date_ajout DATETIME NOT NULL');

        // orders table
        $this->addSql('ALTER TABLE orders DROP FOREIGN KEY `FK_orders_buyer`');
        $this->addSql('ALTER TABLE orders DROP FOREIGN KEY `FK_orders_product`');
        $this->addSql('DROP INDEX buyer_id ON orders');
        $this->addSql('DROP INDEX product_id ON orders');
        $this->addSql('ALTER TABLE orders CHANGE order_date order_date DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT FK_E52FFDEE6C755722 FOREIGN KEY (buyer_id) REFERENCES app_user (user_id)');
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT FK_E52FFDEE4584665A FOREIGN KEY (product_id) REFERENCES product_service (product_id)');
        $this->addSql('CREATE INDEX IDX_E52FFDEE6C755722 ON orders (buyer_id)');
        $this->addSql('CREATE INDEX IDX_E52FFDEE4584665A ON orders (product_id)');
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT `FK_orders_buyer` FOREIGN KEY (buyer_id) REFERENCES app_user (user_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT `FK_orders_product` FOREIGN KEY (product_id) REFERENCES product_service (product_id) ON DELETE CASCADE');

        // commande_status_history
        $this->addSql('ALTER TABLE commande_status_history ADD CONSTRAINT FK_EA31081482EA2E54 FOREIGN KEY (commande_id) REFERENCES commande (commande_id) ON DELETE CASCADE');

        // produit_service table
        $this->addSql('DROP INDEX idx_produit_profile ON produit_service');
        $this->addSql('ALTER TABLE produit_service CHANGE description description LONGTEXT DEFAULT NULL, CHANGE quantite quantite INT NOT NULL, CHANGE owner_user_id owner_user_id INT DEFAULT NULL');

        // product_service table
        $this->addSql('ALTER TABLE product_service DROP FOREIGN KEY `product_service_ibfk_1`');
        $this->addSql('DROP INDEX idx_product_category ON product_service');
        $this->addSql('DROP INDEX idx_product_price ON product_service');
        $this->addSql('DROP INDEX idx_product_stock ON product_service');
        $this->addSql('DROP INDEX idx_product_fournisseur ON product_service');
        $this->addSql('ALTER TABLE product_service DROP FOREIGN KEY `product_service_ibfk_1`');
        $this->addSql('ALTER TABLE product_service CHANGE description description LONGTEXT DEFAULT NULL, CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE product_service ADD CONSTRAINT FK_304481628DE820D9 FOREIGN KEY (seller_id) REFERENCES app_user (user_id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX idx_product_service_seller ON product_service (seller_id)');
        $this->addSql('ALTER TABLE product_service ADD CONSTRAINT `product_service_ibfk_1` FOREIGN KEY (seller_id) REFERENCES app_user (user_id) ON DELETE CASCADE');

        // payment table
        $this->addSql('ALTER TABLE payment DROP amount, CHANGE payment_date payment_date DATETIME NOT NULL, CHANGE payment_method payment_method VARCHAR(255) NOT NULL, CHANGE payment_status payment_status VARCHAR(255) NOT NULL, CHANGE notes notes LONGTEXT NOT NULL');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840D6E1B4FD5 FOREIGN KEY (investment_id) REFERENCES investment (investment_id)');
        $this->addSql('DROP INDEX investment_id ON payment');
        $this->addSql('CREATE INDEX IDX_6D28840D6E1B4FD5 ON payment (investment_id)');

        // participation table
        $this->addSql('DROP INDEX uq_participation_user_formation ON participation');
        $this->addSql('ALTER TABLE participation CHANGE user_id user_id INT DEFAULT NULL, CHANGE date_affectation date_affectation DATETIME DEFAULT NULL, CHANGE remarques remarques LONGTEXT DEFAULT NULL, CHANGE formation_id formation_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE participation ADD CONSTRAINT FK_AB55E24FA76ED395 FOREIGN KEY (user_id) REFERENCES app_user (user_id)');
        $this->addSql('ALTER TABLE participation ADD CONSTRAINT FK_AB55E24F5200282E FOREIGN KEY (formation_id) REFERENCES formation (formation_id)');
        $this->addSql('CREATE INDEX idx_participation_user ON participation (user_id)');
        $this->addSql('CREATE INDEX idx_participation_formation ON participation (formation_id)');
        $this->addSql('ALTER TABLE participation ADD CONSTRAINT `FK_participation_ibfk_1` FOREIGN KEY (user_id) REFERENCES app_user (user_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE participation ADD CONSTRAINT `FK_participation_ibfk_2` FOREIGN KEY (formation_id) REFERENCES formation (formation_id) ON DELETE CASCADE');

        // promo_code table
        $this->addSql('ALTER TABLE promo_code DROP FOREIGN KEY `FK_PROMO_PARTICIPATION_SOURCE`');
        $this->addSql('DROP INDEX fk_promo_participation_source ON promo_code');
        $this->addSql('ALTER TABLE promo_code CHANGE created_at created_at DATETIME NOT NULL, CHANGE expires_at expires_at DATETIME NOT NULL, CHANGE used_at used_at DATETIME DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_3D8C939EFBBB320A ON promo_code (participation_source_id)');
        $this->addSql('ALTER TABLE promo_code ADD CONSTRAINT `FK_PROMO_PARTICIPATION_SOURCE` FOREIGN KEY (participation_source_id) REFERENCES participation (id_candidature) ON DELETE SET NULL');

        // training_request table
        $this->addSql('ALTER TABLE training_request DROP FOREIGN KEY `training_request_ibfk_1`');
        $this->addSql('ALTER TABLE training_request DROP FOREIGN KEY `training_request_ibfk_2`');
        $this->addSql('DROP INDEX uq_training_request_user_formation ON training_request');
        $this->addSql('DROP INDEX idx_request_status ON training_request');
        $this->addSql('DROP INDEX idx_request_startup ON training_request');
        $this->addSql('DROP INDEX idx_request_formation ON training_request');
        $this->addSql('ALTER TABLE training_request DROP FOREIGN KEY `training_request_ibfk_1`');
        $this->addSql('ALTER TABLE training_request DROP FOREIGN KEY `training_request_ibfk_2`');
        $this->addSql('ALTER TABLE training_request CHANGE request_date request_date DATETIME NOT NULL, CHANGE status status VARCHAR(50) DEFAULT \'pending\' NOT NULL');
        $this->addSql('ALTER TABLE training_request ADD CONSTRAINT FK_E6A91F9167B339C5 FOREIGN KEY (startup_id) REFERENCES app_user (user_id)');
        $this->addSql('ALTER TABLE training_request ADD CONSTRAINT FK_E6A91F915200282E FOREIGN KEY (formation_id) REFERENCES formation (formation_id)');
        $this->addSql('CREATE INDEX IDX_E6A91F9167B339C5 ON training_request (startup_id)');
        $this->addSql('CREATE INDEX IDX_E6A91F915200282E ON training_request (formation_id)');
        $this->addSql('ALTER TABLE training_request ADD CONSTRAINT `training_request_ibfk_1` FOREIGN KEY (startup_id) REFERENCES app_user (user_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE training_request ADD CONSTRAINT `training_request_ibfk_2` FOREIGN KEY (formation_id) REFERENCES formation (formation_id) ON DELETE CASCADE');

        // formation table
        $this->addSql('ALTER TABLE formation ADD CONSTRAINT FK_404021BFFB08EDF6 FOREIGN KEY (trainer_id) REFERENCES app_user (user_id)');
        $this->addSql('DROP INDEX idx_404021bffb08edf6 ON formation');
        $this->addSql('CREATE INDEX idx_formation_trainer ON formation (trainer_id)');

        // negotiation table
        $this->addSql('ALTER TABLE negotiation DROP FOREIGN KEY `FK_17989598166D1F9C`');
        $this->addSql('ALTER TABLE negotiation DROP FOREIGN KEY `FK_1798959867B339C5`');
        $this->addSql('ALTER TABLE negotiation DROP FOREIGN KEY `FK_179895989AE528DA`');
        $this->addSql('DROP INDEX idx_negotiation_project ON negotiation');
        $this->addSql('DROP INDEX idx_negotiation_investor ON negotiation');
        $this->addSql('DROP INDEX startup_id ON negotiation');
        $this->addSql('ALTER TABLE negotiation ADD CONSTRAINT FK_17989598166D1F9C FOREIGN KEY (project_id) REFERENCES project (project_id)');
        $this->addSql('ALTER TABLE negotiation ADD CONSTRAINT FK_1798959867B339C5 FOREIGN KEY (startup_id) REFERENCES app_user (user_id)');
        $this->addSql('ALTER TABLE negotiation ADD CONSTRAINT FK_179895989AE528DA FOREIGN KEY (investor_id) REFERENCES app_user (user_id)');
        $this->addSql('CREATE INDEX IDX_17989598166D1F9C ON negotiation (project_id)');
        $this->addSql('CREATE INDEX IDX_179895989AE528DA ON negotiation (investor_id)');
        $this->addSql('CREATE INDEX IDX_1798959867B339C5 ON negotiation (startup_id)');
        $this->addSql('ALTER TABLE negotiation ADD CONSTRAINT `FK_negotiation_ibfk_1` FOREIGN KEY (project_id) REFERENCES project (project_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE negotiation ADD CONSTRAINT `FK_negotiation_ibfk_2` FOREIGN KEY (startup_id) REFERENCES app_user (user_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE negotiation ADD CONSTRAINT `FK_negotiation_ibfk_3` FOREIGN KEY (investor_id) REFERENCES app_user (user_id) ON DELETE CASCADE');

        // negotiation_message table
        $this->addSql('ALTER TABLE negotiation_message DROP FOREIGN KEY `negotiation_message_ibfk_1`');
        $this->addSql('ALTER TABLE negotiation_message DROP FOREIGN KEY `negotiation_message_ibfk_2`');
        $this->addSql('DROP INDEX idx_neg_msg_negotiation ON negotiation_message');
        $this->addSql('DROP INDEX sender_id ON negotiation_message');
        $this->addSql('ALTER TABLE negotiation_message CHANGE message message LONGTEXT NOT NULL, CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE negotiation_message ADD CONSTRAINT FK_A095F86167A34946 FOREIGN KEY (negotiation_id) REFERENCES negotiation (negotiation_id)');
        $this->addSql('ALTER TABLE negotiation_message ADD CONSTRAINT FK_A095F861F624B39D FOREIGN KEY (sender_id) REFERENCES app_user (user_id)');
        $this->addSql('CREATE INDEX IDX_A095F86167A34946 ON negotiation_message (negotiation_id)');
        $this->addSql('CREATE INDEX IDX_A095F861F624B39D ON negotiation_message (sender_id)');
        $this->addSql('ALTER TABLE negotiation_message ADD CONSTRAINT `negotiation_message_ibfk_1` FOREIGN KEY (negotiation_id) REFERENCES negotiation (negotiation_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE negotiation_message ADD CONSTRAINT `negotiation_message_ibfk_2` FOREIGN KEY (sender_id) REFERENCES app_user (user_id) ON DELETE CASCADE');

        // project table
        $this->addSql('ALTER TABLE project DROP FOREIGN KEY `project_ibfk_1`');
        $this->addSql('DROP INDEX idx_project_status ON project');
        $this->addSql('DROP INDEX idx_project_budget ON project');
        $this->addSql('ALTER TABLE project CHANGE description description LONGTEXT DEFAULT NULL, CHANGE problem_description problem_description LONGTEXT DEFAULT NULL, CHANGE solution_description solution_description LONGTEXT DEFAULT NULL, CHANGE competitive_advantage competitive_advantage LONGTEXT DEFAULT NULL, CHANGE created_at created_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE project ADD CONSTRAINT FK_2FB3D0EE67B339C5 FOREIGN KEY (startup_id) REFERENCES app_user (user_id)');
        $this->addSql('CREATE INDEX idx_project_startup ON project (startup_id)');
        $this->addSql('ALTER TABLE project ADD CONSTRAINT `project_ibfk_1` FOREIGN KEY (startup_id) REFERENCES app_user (user_id) ON DELETE CASCADE');

        // deal table
        $this->addSql('ALTER TABLE deal DROP FOREIGN KEY `FK_E3FEC116166D1F9C`');
        $this->addSql('ALTER TABLE deal DROP FOREIGN KEY `FK_E3FEC11667A34946`');
        $this->addSql('ALTER TABLE deal DROP FOREIGN KEY `FK_E3FEC1166C755722`');
        $this->addSql('ALTER TABLE deal DROP FOREIGN KEY `FK_E3FEC1168DE820D9`');
        $this->addSql('DROP INDEX negotiation_id ON deal');
        $this->addSql('DROP INDEX idx_deal_project ON deal');
        $this->addSql('DROP INDEX buyer_id ON deal');
        $this->addSql('DROP INDEX seller_id ON deal');
        $this->addSql('ALTER TABLE deal ADD CONSTRAINT FK_E3FEC116166D1F9C FOREIGN KEY (project_id) REFERENCES project (project_id)');
        $this->addSql('ALTER TABLE deal ADD CONSTRAINT FK_E3FEC11667A34946 FOREIGN KEY (negotiation_id) REFERENCES negotiation (negotiation_id)');
        $this->addSql('ALTER TABLE deal ADD CONSTRAINT FK_E3FEC1166C755722 FOREIGN KEY (buyer_id) REFERENCES app_user (user_id)');
        $this->addSql('ALTER TABLE deal ADD CONSTRAINT FK_E3FEC1168DE820D9 FOREIGN KEY (seller_id) REFERENCES app_user (user_id)');
        $this->addSql('CREATE INDEX IDX_E3FEC11667A34946 ON deal (negotiation_id)');
        $this->addSql('CREATE INDEX IDX_E3FEC116166D1F9C ON deal (project_id)');
        $this->addSql('CREATE INDEX IDX_E3FEC1166C755722 ON deal (buyer_id)');
        $this->addSql('CREATE INDEX IDX_E3FEC1168DE820D9 ON deal (seller_id)');
        $this->addSql('ALTER TABLE deal ADD CONSTRAINT `FK_deal_ibfk_1` FOREIGN KEY (project_id) REFERENCES project (project_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE deal ADD CONSTRAINT `FK_deal_ibfk_2` FOREIGN KEY (negotiation_id) REFERENCES negotiation (negotiation_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE deal ADD CONSTRAINT `FK_deal_ibfk_3` FOREIGN KEY (buyer_id) REFERENCES app_user (user_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE deal ADD CONSTRAINT `FK_deal_ibfk_4` FOREIGN KEY (seller_id) REFERENCES app_user (user_id) ON DELETE CASCADE');

        // avis table
        $this->addSql('ALTER TABLE avis DROP FOREIGN KEY `FK_8F91ABF05200282E`');
        $this->addSql('ALTER TABLE avis DROP FOREIGN KEY `FK_8F91ABF070574616`');
        $this->addSql('DROP INDEX idx_8f91abf070574616 ON avis');
        $this->addSql('DROP INDEX idx_8f91abf05200282e ON avis');
        $this->addSql('ALTER TABLE avis ADD CONSTRAINT FK_8F91ABF05200282E FOREIGN KEY (formation_id) REFERENCES formation (formation_id)');
        $this->addSql('ALTER TABLE avis ADD CONSTRAINT FK_8F91ABF070574616 FOREIGN KEY (reviewer_id) REFERENCES app_user (user_id)');
        $this->addSql('CREATE INDEX idx_avis_reviewer ON avis (reviewer_id)');
        $this->addSql('CREATE INDEX idx_avis_formation ON avis (formation_id)');
        $this->addSql('ALTER TABLE avis ADD CONSTRAINT `FK_avis_ibfk_1` FOREIGN KEY (formation_id) REFERENCES formation (formation_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE avis ADD CONSTRAINT `FK_avis_ibfk_2` FOREIGN KEY (reviewer_id) REFERENCES app_user (user_id) ON DELETE CASCADE');

        // commentaire table
        $this->addSql('ALTER TABLE commentaire DROP FOREIGN KEY `FK_67F068BC4B89032C`');
        $this->addSql('ALTER TABLE commentaire DROP FOREIGN KEY `FK_67F068BCA76ED395`');
        $this->addSql('DROP INDEX idx_67f068bc4b89032c ON commentaire');
        $this->addSql('DROP INDEX idx_67f068bca76ed395 ON commentaire');
        $this->addSql('ALTER TABLE commentaire ADD CONSTRAINT FK_67F068BC4B89032C FOREIGN KEY (post_id) REFERENCES post (post_id)');
        $this->addSql('ALTER TABLE commentaire ADD CONSTRAINT FK_67F068BCA76ED395 FOREIGN KEY (user_id) REFERENCES app_user (user_id)');
        $this->addSql('CREATE INDEX idx_commentaire_post ON commentaire (post_id)');
        $this->addSql('CREATE INDEX idx_commentaire_user ON commentaire (user_id)');
        $this->addSql('ALTER TABLE commentaire ADD CONSTRAINT `FK_commentaire_ibfk_1` FOREIGN KEY (post_id) REFERENCES post (post_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE commentaire ADD CONSTRAINT `FK_commentaire_ibfk_2` FOREIGN KEY (user_id) REFERENCES app_user (user_id) ON DELETE CASCADE');

        // post table
        $this->addSql('ALTER TABLE post CHANGE user_id user_id INT DEFAULT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE media_type media_type VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE post ADD CONSTRAINT FK_5A8A6C8DA76ED395 FOREIGN KEY (user_id) REFERENCES app_user (user_id)');
        $this->addSql('CREATE INDEX idx_post_user ON post (user_id)');

        // reaction table
        $this->addSql('ALTER TABLE reaction CHANGE post_id post_id INT DEFAULT NULL, CHANGE user_id user_id INT DEFAULT NULL, CHANGE type type VARCHAR(50) NOT NULL, CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE reaction ADD CONSTRAINT FK_A4D707F74B89032C FOREIGN KEY (post_id) REFERENCES post (post_id)');
        $this->addSql('ALTER TABLE reaction ADD CONSTRAINT FK_A4D707F7A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (user_id)');
        $this->addSql('CREATE INDEX IDX_A4D707F74B89032C ON reaction (post_id)');
        $this->addSql('CREATE INDEX IDX_A4D707F7A76ED395 ON reaction (user_id)');
        $this->addSql('ALTER TABLE reaction ADD CONSTRAINT `FK_reaction_ibfk_1` FOREIGN KEY (post_id) REFERENCES post (post_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reaction ADD CONSTRAINT `FK_reaction_ibfk_2` FOREIGN KEY (user_id) REFERENCES app_user (user_id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // Down migration - revert to previous state (simplified)
        $this->addSql('ALTER TABLE reaction DROP FOREIGN KEY FK_A4D707F7A76ED395');
        $this->addSql('ALTER TABLE reaction DROP FOREIGN KEY FK_A4D707F74B89032C');
        $this->addSql('ALTER TABLE post DROP FOREIGN KEY FK_5A8A6C8DA76ED395');
        $this->addSql('ALTER TABLE commentaire DROP FOREIGN KEY FK_67F068BCA76ED395');
        $this->addSql('ALTER TABLE commentaire DROP FOREIGN KEY FK_67F068BC4B89032C');
        $this->addSql('ALTER TABLE avis DROP FOREIGN KEY FK_8F91ABF070574616');
        $this->addSql('ALTER TABLE avis DROP FOREIGN KEY FK_8F91ABF05200282E');
        $this->addSql('ALTER TABLE deal DROP FOREIGN KEY FK_E3FEC1168DE820D9');
        $this->addSql('ALTER TABLE deal DROP FOREIGN KEY FK_E3FEC1166C755722');
        $this->addSql('ALTER TABLE deal DROP FOREIGN KEY FK_E3FEC11667A34946');
        $this->addSql('ALTER TABLE deal DROP FOREIGN KEY FK_E3FEC116166D1F9C');
        $this->addSql('ALTER TABLE project DROP FOREIGN KEY FK_2FB3D0EE67B339C5');
        $this->addSql('ALTER TABLE negotiation_message DROP FOREIGN KEY FK_A095F861F624B39D');
        $this->addSql('ALTER TABLE negotiation_message DROP FOREIGN KEY FK_A095F86167A34946');
        $this->addSql('ALTER TABLE negotiation DROP FOREIGN KEY FK_179895989AE528DA');
        $this->addSql('ALTER TABLE negotiation DROP FOREIGN KEY FK_1798959867B339C5');
        $this->addSql('ALTER TABLE negotiation DROP FOREIGN KEY FK_17989598166D1F9C');
        $this->addSql('ALTER TABLE formation DROP FOREIGN KEY FK_404021BFFB08EDF6');
        $this->addSql('ALTER TABLE training_request DROP FOREIGN KEY FK_E6A91F915200282E');
        $this->addSql('ALTER TABLE training_request DROP FOREIGN KEY FK_E6A91F9167B339C5');
        $this->addSql('ALTER TABLE promo_code DROP FOREIGN KEY FK_3D8C939EFBBB320A');
        $this->addSql('ALTER TABLE participation DROP FOREIGN KEY FK_AB55E24F5200282E');
        $this->addSql('ALTER TABLE participation DROP FOREIGN KEY FK_AB55E24FA76ED395');
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840D6E1B4FD5');
        $this->addSql('ALTER TABLE product_service DROP FOREIGN KEY FK_304481628DE820D9');
        $this->addSql('ALTER TABLE orders DROP FOREIGN KEY FK_E52FFDEE4584665A');
        $this->addSql('ALTER TABLE orders DROP FOREIGN KEY FK_E52FFDEE6C755722');
        $this->addSql('ALTER TABLE commande_status_history DROP FOREIGN KEY FK_EA31081482EA2E54');
    }
}
