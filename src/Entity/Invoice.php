<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'invoice')]
class Invoice
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\Column(length: 30, unique: true)] private string $number = '';
    #[ORM\ManyToOne(targetEntity: Vehicle::class)] #[ORM\JoinColumn(nullable: false)] private ?Vehicle $vehicle = null;
    #[ORM\ManyToOne(targetEntity: Repair::class)] #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')] private ?Repair $repair = null;
    #[ORM\Column] private \DateTimeImmutable $issuedAt;
    #[ORM\Column(length: 30)] private string $status = 'Brouillon';
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)] private string $laborAmount = '0.00';
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)] private string $partsAmount = '0.00';
    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)] private string $taxRate = '19.00';
    #[ORM\Column(type: 'text', nullable: true)] private ?string $notes = null;
    public function __construct() { $this->issuedAt = new \DateTimeImmutable(); $this->number = 'FAC-'.date('Ymd-His').'-'.random_int(10,99); }
    public function getId(): ?int { return $this->id; }
    public function getNumber(): string { return $this->number; }
    public function getVehicle(): ?Vehicle { return $this->vehicle; }
    public function setVehicle(?Vehicle $v): self { $this->vehicle = $v; return $this; }
    public function getRepair(): ?Repair { return $this->repair; }
    public function setRepair(?Repair $v): self { $this->repair = $v; return $this; }
    public function getIssuedAt(): \DateTimeImmutable { return $this->issuedAt; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): self { $this->status = $v; return $this; }
    public function getLaborAmount(): string { return $this->laborAmount; }
    public function setLaborAmount(string $v): self { $this->laborAmount = $v; return $this; }
    public function getPartsAmount(): string { return $this->partsAmount; }
    public function setPartsAmount(string $v): self { $this->partsAmount = $v; return $this; }
    public function getTaxRate(): string { return $this->taxRate; }
    public function setTaxRate(string $v): self { $this->taxRate = $v; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $v): self { $this->notes = $v; return $this; }
    public function getSubtotal(): float { return (float)$this->laborAmount + (float)$this->partsAmount; }
    public function getTaxAmount(): float { return $this->getSubtotal() * (float)$this->taxRate / 100; }
    public function getTotal(): float { return $this->getSubtotal() + $this->getTaxAmount(); }
}
