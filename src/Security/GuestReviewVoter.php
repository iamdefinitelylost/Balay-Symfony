<?php

namespace App\Security;

use App\Entity\Booking;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Who may review the guest of a booking?
 *  - the owner of the stay (the staff member who created the listing)
 *  - admin: any booking
 * (Whether the stay has ended is checked in the controller, so the person gets a clear message.)
 */
class GuestReviewVoter extends Voter
{
    public const REVIEW = 'GUEST_REVIEW';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::REVIEW && $subject instanceof Booking;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }
        $roles = $user->getRoles();
        if (in_array('ROLE_ADMIN', $roles, true)) {
            return true;
        }
        $owner = $subject->getListing()->getCreatedBy();

        return in_array('ROLE_STAFF', $roles, true) && $owner !== null && $owner->getId() === $user->getId();
    }
}
