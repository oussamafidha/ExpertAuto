<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'repair_status')]
class RepairStateTransition
{
    #[ORM\Id]
    #[ORM\Column(name: 'state_name', length: 40)]
    private string $stateName;

    #[ORM\Id]
    #[ORM\Column(name: 'next_state', length: 40)]
    private string $nextState;

    public function __construct(string $stateName, string $nextState)
    {
        $this->stateName = $stateName;
        $this->nextState = $nextState;
    }

    public function getStateName(): string
    {
        return $this->stateName;
    }

    public function getNextState(): string
    {
        return $this->nextState;
    }
}
