<?php

namespace App\Security;

use App\Entity\Listing;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Who may edit or delete a record (a stay)?
 *  - admin: any record
 *  - staff: only records they created
 * Use: $this->denyAccessUnlessGranted('RECORD_MANAGE', $record)  /  {{ is_granted('RECORD_MANAGE', record) }}
 */
class RecordVoter extends Voter
{
    public const MANAGE = 'RECORD_MANAGE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::MANAGE && $subject instanceof Listing;
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

        $owner = $subject->getCreatedBy();

        return in_array('ROLE_STAFF', $roles, true) && $owner !== null && $owner->getId() === $user->getId();
    }
}
