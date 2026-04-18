<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds 10 enriched business fields to the `project` table for richer AI analysis.
 * Also converts required_budget from DECIMAL to DOUBLE to match PHP float type.
 */
final class Version20260414000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add enriched business fields to project table (problem, solution, market, team, etc.)';
    }

    public function up(Schema $schema): void
    {
        // Add new business fields (skip if already exists via IF NOT EXISTS pattern)
        $this->addSql("
            ALTER TABLE project
                ADD COLUMN IF NOT EXISTS problem_description     LONGTEXT    DEFAULT NULL,
                ADD COLUMN IF NOT EXISTS solution_description    LONGTEXT    DEFAULT NULL,
                ADD COLUMN IF NOT EXISTS target_audience         LONGTEXT    DEFAULT NULL,
                ADD COLUMN IF NOT EXISTS business_model          VARCHAR(50) DEFAULT NULL,
                ADD COLUMN IF NOT EXISTS market_scope            VARCHAR(30) DEFAULT NULL,
                ADD COLUMN IF NOT EXISTS competitive_advantage   LONGTEXT    DEFAULT NULL,
                ADD COLUMN IF NOT EXISTS project_stage           VARCHAR(30) DEFAULT NULL,
                ADD COLUMN IF NOT EXISTS funding_usage           LONGTEXT    DEFAULT NULL,
                ADD COLUMN IF NOT EXISTS financial_forecast      LONGTEXT    DEFAULT NULL,
                ADD COLUMN IF NOT EXISTS team_description        VARCHAR(500) DEFAULT NULL
        ");

        // Convert required_budget from DECIMAL to DOUBLE to match PHP float property
        $this->addSql("
            ALTER TABLE project
                MODIFY COLUMN required_budget DOUBLE NOT NULL
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("
            ALTER TABLE project
                DROP COLUMN IF EXISTS problem_description,
                DROP COLUMN IF EXISTS solution_description,
                DROP COLUMN IF EXISTS target_audience,
                DROP COLUMN IF EXISTS business_model,
                DROP COLUMN IF EXISTS market_scope,
                DROP COLUMN IF EXISTS competitive_advantage,
                DROP COLUMN IF EXISTS project_stage,
                DROP COLUMN IF EXISTS funding_usage,
                DROP COLUMN IF EXISTS financial_forecast,
                DROP COLUMN IF EXISTS team_description
        ");

        $this->addSql("
            ALTER TABLE project
                MODIFY COLUMN required_budget DECIMAL(15,2) NOT NULL
        ");
    }
}
