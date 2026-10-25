<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'repair_part')]
class RepairPart
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\ManyToOne(targetEntity: Repair::class)] #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private ?Repair $repair = null;
    #[ORM\ManyToOne(targetEntity: Part::class)] #[ORM\JoinColumn(nullable: false)] private ?Part $part = null;
    #[ORM\Column] #[Assert\Positive] private int $quantity = 1;
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)] private string $unitPrice = '0.00';
    public function getId(): ?int { return $this->id; }
    public function getRepair(): ?Repair { return $this->repair; }
    public function setRepair(?Repair $v): self { $this->repair = $v; return $this; }
    public function getPart(): ?Part { return $this->part; }
    public function setPart(?Part $v): self { $this->part = $v; return $this; }
    public function getQuantity(): int { return $this->quantity; }
    public function setQuantity(int $v): self { $this->quantity = $v; return $this; }
    public function getUnitPrice(): string { return $this->unitPrice; }
    public function setUnitPrice(string $v): self { $this->unitPrice = $v; return $this; }
}
