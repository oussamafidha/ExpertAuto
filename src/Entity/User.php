<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'app_user')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank, Assert\Email]
    private string $email = '';

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    private string $fullName = '';

    #[ORM\Column]
    private array $roles = ['ROLE_MECHANIC'];

    #[ORM\Column]
    private string $password = '';

    public function getId(): ?int { return $this->id; }
    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): self { $this->email = mb_strtolower(trim($email)); return $this; }
    public function getUserIdentifier(): string { return $this->email; }
    public function getFullName(): string { return $this->fullName; }
    public function setFullName(string $name): self { $this->fullName = trim($name); return $this; }
    public function getRoles(): array { $roles = $this->roles; $roles[] = 'ROLE_USER'; return array_values(array_unique($roles)); }
    public function setRoles(array $roles): self { $this->roles = $roles; return $this; }
    public function getPassword(): string { return $this->password; }
    public function setPassword(string $password): self { $this->password = $password; return $this; }
    public function eraseCredentials(): void {}
}
