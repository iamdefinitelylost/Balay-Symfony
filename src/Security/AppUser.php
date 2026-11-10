<?php

namespace App\Security;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/** The logged-in user, loaded from the `users` table. role: customer | staff | admin */
final class AppUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    public function __construct(
        private int $id,
        private string $name,
        private string $email,
        private string $password,
        private string $role,
        private string $status,
    ) {
    }

    public static function fromRow(array $r): self
    {
        return new self((int) $r['id'], $r['name'], $r['email'], $r['password_hash'], $r['role'], $r['status']);
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getPassword(): string
    {
        return $this->password;
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
