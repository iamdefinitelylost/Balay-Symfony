<?php

namespace App\Security;

use Doctrine\DBAL\Connection;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/** @implements UserProviderInterface<AppUser> */
class UserProvider implements UserProviderInterface
{
    public function __construct(private Connection $db)
    {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $row = $this->db->fetchAssociative('SELECT * FROM users WHERE email = ?', [strtolower(trim($identifier))]);
        if (!$row) {
            throw new UserNotFoundException();
        }

        return AppUser::fromRow($row);
    }

    /** Runs on every request: role changes apply immediately, disabled/deleted accounts are logged out. */
    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof AppUser) {
            throw new UnsupportedUserException(sprintf('Unsupported user class "%s".', $user::class));
        }
        $row = $this->db->fetchAssociative('SELECT * FROM users WHERE id = ?', [$user->getId()]);
        if (!$row || $row['status'] !== 'active') {
            throw new UserNotFoundException();
        }

        return AppUser::fromRow($row);
    }

    public function supportsClass(string $class): bool
    {
        return $class === AppUser::class || is_subclass_of($class, AppUser::class);
    }
}
