<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'vehicle')]
class Vehicle
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\Column(length: 20, unique: true)] #[Assert\NotBlank] private string $registrationNumber = '';
    #[ORM\Column(length: 80)] #[Assert\NotBlank] private string $make = '';
    #[ORM\Column(length: 80)] #[Assert\NotBlank] private string $model = '';
    #[ORM\Column(length: 120)] #[Assert\NotBlank] private string $ownerName = '';
    #[ORM\Column(length: 30, nullable: true)] private ?string $ownerPhone = null;
    #[ORM\Column(type: 'text', nullable: true)] private ?string $notes = null;
    #[ORM\Column] private \DateTimeImmutable $createdAt;
    public function __construct() { $this->createdAt = new \DateTimeImmutable(); }
    public function getId(): ?int { return $this->id; }
    public function getRegistrationNumber(): string { return $this->registrationNumber; }
    public function setRegistrationNumber(string $v): self { $this->registrationNumber = mb_strtoupper(trim($v)); return $this; }
    public function getMake(): string { return $this->make; }
    public function setMake(string $v): self { $this->make = trim($v); return $this; }
    public function getModel(): string { return $this->model; }
    public function setModel(string $v): self { $this->model = trim($v); return $this; }
    public function getOwnerName(): string { return $this->ownerName; }
    public function setOwnerName(string $v): self { $this->ownerName = trim($v); return $this; }
    public function getOwnerPhone(): ?string { return $this->ownerPhone; }
    public function setOwnerPhone(?string $v): self { $this->ownerPhone = $v ?: null; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $v): self { $this->notes = $v; return $this; }
}
