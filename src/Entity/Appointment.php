<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'appointment')]
class Appointment
{
    public const STATUSES = ['Confirmé', 'En attente', 'En réparation', 'Terminé', 'Annulé'];
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\ManyToOne(targetEntity: Vehicle::class)] #[ORM\JoinColumn(nullable: false)] #[Assert\NotNull] private ?Vehicle $vehicle = null;
    #[ORM\Column] #[Assert\NotNull] private \DateTimeImmutable $appointmentAt;
    #[ORM\Column(length: 180)] #[Assert\NotBlank] private string $service = '';
    #[ORM\Column(length: 40)] private string $status = 'Confirmé';
    #[ORM\ManyToOne(targetEntity: User::class)] #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')] private ?User $mechanic = null;
    #[ORM\Column(type: 'text', nullable: true)] private ?string $notes = null;
    public function __construct() { $this->appointmentAt = new \DateTimeImmutable('+1 hour'); }
    public function getId(): ?int { return $this->id; }
    public function getVehicle(): ?Vehicle { return $this->vehicle; }
    public function setVehicle(?Vehicle $v): self { $this->vehicle = $v; return $this; }
    public function getAppointmentAt(): \DateTimeImmutable { return $this->appointmentAt; }
    public function setAppointmentAt(\DateTimeImmutable $d): self { $this->appointmentAt = $d; return $this; }
    public function getService(): string { return $this->service; }
    public function setService(string $v): self { $this->service = trim($v); return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): self { $this->status = $v; return $this; }
    public function getMechanic(): ?User { return $this->mechanic; }
    public function setMechanic(?User $v): self { $this->mechanic = $v; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $v): self { $this->notes = $v; return $this; }
}
