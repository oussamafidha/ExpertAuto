<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261025195000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align repair status history index name with Doctrine mapping.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE repair_status_history RENAME INDEX IDX_6B05620CDA4A219 TO IDX_97815DB943833CFF');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE repair_status_history RENAME INDEX IDX_97815DB943833CFF TO IDX_6B05620CDA4A219');
    }
}
