<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration to add missing indexes on FK columns (N+1 prevention).
 */
final class Version20260507104500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add missing @Index annotations on FK columns for N+1 prevention';
    }

    public function up(Schema $schema): void
    {
        // Investment entity - idx_investment_project, idx_investment_investor
        $this->addSql('CREATE INDEX idx_investment_project ON investment (project_id)');
        $this->addSql('CREATE INDEX idx_investment_investor ON investment (investor_id)');

        // Project entity - idx_project_startup
        $this->addSql('CREATE INDEX idx_project_startup ON project (startup_id)');

        // Post entity - idx_post_user
        $this->addSql('CREATE INDEX idx_post_user ON post (user_id)');

        // Commentaire entity - idx_commentaire_post, idx_commentaire_user
        $this->addSql('CREATE INDEX idx_commentaire_post ON commentaire (post_id)');
        $this->addSql('CREATE INDEX idx_commentaire_user ON commentaire (user_id)');

        // Participation entity - idx_participation_user, idx_participation_formation
        $this->addSql('CREATE INDEX idx_participation_user ON participation (user_id)');
        $this->addSql('CREATE INDEX idx_participation_formation ON participation (formation_id)');

        // Formation entity - idx_formation_trainer
        $this->addSql('CREATE INDEX idx_formation_trainer ON formation (trainer_id)');

        // ProductService entity - idx_product_service_seller
        $this->addSql('CREATE INDEX idx_product_service_seller ON product_service (seller_id)');

        // Avis entity - idx_avis_reviewer, idx_avis_formation
        $this->addSql('CREATE INDEX idx_avis_reviewer ON avis (reviewer_id)');
        $this->addSql('CREATE INDEX idx_avis_formation ON avis (formation_id)');
    }

    public function down(Schema $schema): void
    {
        // Drop indexes in reverse order
        $this->addSql('DROP INDEX idx_avis_formation ON avis');
        $this->addSql('DROP INDEX idx_avis_reviewer ON avis');
        $this->addSql('DROP INDEX idx_product_service_seller ON product_service');
        $this->addSql('DROP INDEX idx_formation_trainer ON formation');
        $this->addSql('DROP INDEX idx_participation_formation ON participation');
        $this->addSql('DROP INDEX idx_participation_user ON participation');
        $this->addSql('DROP INDEX idx_commentaire_user ON commentaire');
        $this->addSql('DROP INDEX idx_commentaire_post ON commentaire');
        $this->addSql('DROP INDEX idx_post_user ON post');
        $this->addSql('DROP INDEX idx_project_startup ON project');
        $this->addSql('DROP INDEX idx_investment_investor ON investment');
        $this->addSql('DROP INDEX idx_investment_project ON investment');
    }
}
