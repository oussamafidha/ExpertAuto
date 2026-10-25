<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'repair_status_history')]
class RepairStatusHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Repair::class)]
    #[ORM\JoinColumn(name: 'repair_id', nullable: false, onDelete: 'CASCADE')]
    private Repair $repair;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'changed_by_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $changedBy;

    #[ORM\Column(name: 'previous_status', length: 40, nullable: true)]
    private ?string $previousStatus;

    #[ORM\Column(name: 'new_status', length: 40)]
    private string $newStatus;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(Repair $repair, ?string $previousStatus, string $newStatus, ?User $changedBy)
    {
        $this->repair = $repair;
        $this->previousStatus = $previousStatus;
        $this->newStatus = $newStatus;
        $this->changedBy = $changedBy;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRepair(): Repair
    {
        return $this->repair;
    }

    public function getPreviousStatus(): ?string
    {
        return $this->previousStatus;
    }

    public function getNewStatus(): string
    {
        return $this->newStatus;
    }

    public function getChangedBy(): ?User
    {
        return $this->changedBy;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
