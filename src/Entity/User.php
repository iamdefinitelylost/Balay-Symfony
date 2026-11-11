<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/** An account: customer (books stays), staff (manages own records) or admin (manages everything). */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const ROLES = ['admin', 'staff', 'customer'];
    public const STATUSES = ['active', 'disabled'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(length: 80)]
    private string $name = '';

    #[ORM\Column(length: 120, unique: true)]
    private string $email = '';

    #[ORM\Column(name: 'password_hash', length: 255)]
    private string $password = '';

    #[ORM\Column(length: 20)]
    private string $role = 'customer';

    #[ORM\Column(length: 20)]
    private string $status = 'active';

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    /** Always pass a hash made by UserPasswordHasherInterface, never a plain-text password. */
    public function setPassword(string $hash): static
    {
        $this->password = $hash;

        return $this;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /** @return string[] e.g. ['ROLE_USER', 'ROLE_STAFF'] */
    public function getRoles(): array
    {
        return ['ROLE_USER', 'ROLE_' . strtoupper($this->role)];
    }

    /** Shape used by the public site's JavaScript (window.ME). */
    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'email' => $this->email, 'role' => $this->role];
    }

    public function eraseCredentials(): void
    {
    }

    /** Keep the password hash out of the session file (Symfony's recommended pattern). */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0" . self::class . "\0password"] = hash('crc32c', $this->password);

        return $data;
    }
}
