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
    public const ROLE_USER = 'ROLE_USER';
    public const ROLE_ADMIN = 'ROLE_ADMIN';
    public const ROLE_MECHANIC = 'ROLE_MECHANIC';
    public const ROLE_CLIENT = 'ROLE_CLIENT';
    public const ROLE_STOREKEEPER = 'ROLE_STOREKEEPER';

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank, Assert\Email]
    private string $email = '';

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    private string $fullName = '';

    #[ORM\Column]
    private array $roles = [self::ROLE_CLIENT];

    #[ORM\Column]
    private string $password = '';

    public function getId(): ?int { return $this->id; }
    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): self { $this->email = mb_strtolower(trim($email)); return $this; }
    public function getUserIdentifier(): string { return $this->email; }
    public function getFullName(): string { return $this->fullName; }
    public function setFullName(string $name): self { $this->fullName = trim($name); return $this; }
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = self::ROLE_USER;

        return array_values(array_unique(array_map('strtoupper', $roles)));
    }
    public function setRoles(array $roles): self
    {
        $normalized = array_map(static fn (string $role): string => strtoupper(trim($role)), $roles);
        $normalized[] = self::ROLE_USER;

        $this->roles = array_values(array_unique($normalized));

        return $this;
    }
    public function getPassword(): string { return $this->password; }
    public function setPassword(string $password): self { $this->password = $password; return $this; }
    public function eraseCredentials(): void {}
}
