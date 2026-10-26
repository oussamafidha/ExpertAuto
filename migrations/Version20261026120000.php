<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261026120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Associate vehicles with client user accounts.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE vehicle ADD client_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_1D6C1C9E19EB6921 ON vehicle (client_id)');
        $this->addSql('ALTER TABLE vehicle ADD CONSTRAINT FK_1D6C1C9E19EB6921 FOREIGN KEY (client_id) REFERENCES app_user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE vehicle DROP FOREIGN KEY FK_1D6C1C9E19EB6921');
        $this->addSql('DROP INDEX IDX_1D6C1C9E19EB6921 ON vehicle');
        $this->addSql('ALTER TABLE vehicle DROP client_id');
    }
}
