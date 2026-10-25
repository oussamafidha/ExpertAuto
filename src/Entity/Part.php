<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'part')]
class Part
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\Column(length: 80, unique: true)] #[Assert\NotBlank] private string $reference = '';
    #[ORM\Column(length: 150)] #[Assert\NotBlank] private string $name = '';
    #[ORM\Column] #[Assert\PositiveOrZero] private int $stock = 0;
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)] #[Assert\PositiveOrZero] private string $unitPrice = '0.00';
    #[ORM\Column(length: 100, nullable: true)] private ?string $supplier = null;
    #[ORM\Column] #[Assert\PositiveOrZero] private int $alertThreshold = 2;
    public function getId(): ?int { return $this->id; }
    public function getReference(): string { return $this->reference; }
    public function setReference(string $v): self { $this->reference = trim($v); return $this; }
    public function getName(): string { return $this->name; }
    public function setName(string $v): self { $this->name = trim($v); return $this; }
    public function getStock(): int { return $this->stock; }
    public function setStock(int $v): self { $this->stock = $v; return $this; }
    public function getUnitPrice(): string { return $this->unitPrice; }
    public function setUnitPrice(string $v): self { $this->unitPrice = $v; return $this; }
    public function getSupplier(): ?string { return $this->supplier; }
    public function setSupplier(?string $v): self { $this->supplier = $v ?: null; return $this; }
    public function getAlertThreshold(): int { return $this->alertThreshold; }
    public function setAlertThreshold(int $v): self { $this->alertThreshold = $v; return $this; }
}
