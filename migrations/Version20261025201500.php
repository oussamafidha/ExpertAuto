<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261025201500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow newly added repairs to move directly to in-progress.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("DELETE FROM repair_status WHERE state_name = 'Ajouté' AND next_state = 'En attente'");
        $this->addSql("INSERT INTO repair_status (state_name, next_state) VALUES ('Ajouté', 'En réparation')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM repair_status WHERE state_name = 'Ajouté' AND next_state = 'En réparation'");
        $this->addSql("INSERT INTO repair_status (state_name, next_state) VALUES ('Ajouté', 'En attente')");
    }
}
