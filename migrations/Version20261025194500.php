<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261025194500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename repair status transition table and add repair status history.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('RENAME TABLE etat_reparation TO repair_status');
        $this->addSql('ALTER TABLE repair_status CHANGE nom_etat state_name VARCHAR(40) NOT NULL, CHANGE etat_suivant next_state VARCHAR(40) NOT NULL');
        $this->addSql('CREATE TABLE repair_status_history (id INT AUTO_INCREMENT NOT NULL, repair_id INT NOT NULL, previous_status VARCHAR(40) DEFAULT NULL, new_status VARCHAR(40) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_6B05620CDA4A219 (repair_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE repair_status_history ADD CONSTRAINT FK_6B05620CDA4A219 FOREIGN KEY (repair_id) REFERENCES repair (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE repair_status_history DROP FOREIGN KEY FK_6B05620CDA4A219');
        $this->addSql('DROP TABLE repair_status_history');
        $this->addSql('ALTER TABLE repair_status CHANGE state_name nom_etat VARCHAR(40) NOT NULL, CHANGE next_state etat_suivant VARCHAR(40) NOT NULL');
        $this->addSql('RENAME TABLE repair_status TO etat_reparation');
    }
}
