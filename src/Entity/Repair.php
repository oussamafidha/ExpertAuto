<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'repair')]
class Repair
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\ManyToOne(targetEntity: Vehicle::class)] #[ORM\JoinColumn(nullable: false)] private ?Vehicle $vehicle = null;
    #[ORM\ManyToOne(targetEntity: User::class)] #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')] private ?User $mechanic = null;
    #[ORM\Column(length: 180)] #[Assert\NotBlank] private string $title = '';
    #[ORM\Column(type: 'text', nullable: true)] private ?string $description = null;
    #[ORM\Column(length: 40)] private string $status = 'Ajouté';
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)] private string $laborCost = '0.00';
    #[ORM\Column] private \DateTimeImmutable $openedAt;
    #[ORM\Column(nullable: true)] private ?\DateTimeImmutable $closedAt = null;
    public function __construct() { $this->openedAt = new \DateTimeImmutable(); }
    public function getId(): ?int { return $this->id; }
    public function getVehicle(): ?Vehicle { return $this->vehicle; }
    public function setVehicle(?Vehicle $v): self { $this->vehicle = $v; return $this; }
    public function getMechanic(): ?User { return $this->mechanic; }
    public function setMechanic(?User $v): self { $this->mechanic = $v; return $this; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $v): self { $this->title = trim($v); return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $v): self { $this->description = $v; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): self { $this->status = $v; return $this; }
    public function getLaborCost(): string { return $this->laborCost; }
    public function setLaborCost(string $v): self { $this->laborCost = $v; return $this; }
    public function getOpenedAt(): \DateTimeImmutable { return $this->openedAt; }
    public function getClosedAt(): ?\DateTimeImmutable { return $this->closedAt; }
    public function setClosedAt(?\DateTimeImmutable $v): self { $this->closedAt = $v; return $this; }
}
