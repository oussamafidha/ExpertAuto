<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261025190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create repair state transitions and migrate completed repairs to the new state name.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE etat_reparation (nom_etat VARCHAR(40) NOT NULL, etat_suivant VARCHAR(40) NOT NULL, PRIMARY KEY(nom_etat, etat_suivant)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $states = ['Ajouté', 'En attente', 'En réparation', 'Réparé', 'Annulé'];
        $mainTransitions = [
            'Ajouté' => 'En réparation',
            'En attente' => 'En réparation',
            'En réparation' => 'Réparé',
        ];

        foreach ($states as $state) {
            $nextStates = [];
            if (isset($mainTransitions[$state])) {
                $nextStates[] = $mainTransitions[$state];
            }
            if (!in_array($state, ['Ajouté', 'En attente'], true)) {
                $nextStates[] = 'En attente';
            }
            if ($state !== 'Annulé') {
                $nextStates[] = 'Annulé';
            }

            foreach (array_unique($nextStates) as $nextState) {
                $this->addSql(
                    'INSERT INTO etat_reparation (nom_etat, etat_suivant) VALUES (?, ?)',
                    [$state, $nextState]
                );
            }
        }

        $this->addSql("UPDATE repair SET status = 'Réparé' WHERE status = 'Terminée'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE repair SET status = 'Terminée' WHERE status = 'Réparé'");
        $this->addSql('DROP TABLE etat_reparation');
    }
}
