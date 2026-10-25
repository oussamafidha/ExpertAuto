<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261025195800 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Record the user responsible for each repair status history entry.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE repair_status_history ADD changed_by_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_97815DB9828AD0A0 ON repair_status_history (changed_by_id)');
        $this->addSql('ALTER TABLE repair_status_history ADD CONSTRAINT FK_97815DB9828AD0A0 FOREIGN KEY (changed_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE repair_status_history DROP FOREIGN KEY FK_97815DB9828AD0A0');
        $this->addSql('DROP INDEX IDX_97815DB9828AD0A0 ON repair_status_history');
        $this->addSql('ALTER TABLE repair_status_history DROP changed_by_id');
    }
}
