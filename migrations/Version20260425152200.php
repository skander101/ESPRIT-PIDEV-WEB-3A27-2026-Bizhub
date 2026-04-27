<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260425152200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add FK constraint for commande_ligne referencing commande';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commande_ligne ADD CONSTRAINT FK_6E98044082EA2E54 FOREIGN KEY (commande_id) REFERENCES commande (commande_id)');
        $this->addSql('CREATE INDEX IDX_6E98044082EA2E54 ON commande_ligne (commande_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commande_ligne DROP FOREIGN KEY FK_6E98044082EA2E54');
        $this->addSql('DROP INDEX IDX_6E98044082EA2E54 ON commande_ligne');
    }
}