<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/** @implements UserProviderInterface<User> */
class UserProvider implements UserProviderInterface
{
    public function __construct(private UserRepository $users)
    {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        return $this->users->findOneByEmail($identifier) ?? throw new UserNotFoundException();
    }

    /** Runs on every request: role changes apply immediately, disabled/deleted accounts are logged out. */
    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Unsupported user class "%s".', $user::class));
        }
        $fresh = $this->users->find($user->getId());
        if (!$fresh || !$fresh->isActive()) {
            throw new UserNotFoundException();
        }

        return $fresh;
    }

    public function supportsClass(string $class): bool
    {
        return $class === User::class || is_subclass_of($class, User::class);
    }
}
