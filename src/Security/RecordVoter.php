<?php

namespace App\Security;

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
        return $attribute === self::MANAGE && is_array($subject) && array_key_exists('created_by', $subject);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof AppUser) {
            return false;
        }
        $roles = $user->getRoles();
        if (in_array('ROLE_ADMIN', $roles, true)) {
            return true;
        }

        return in_array('ROLE_STAFF', $roles, true)
            && $subject['created_by'] !== null
            && (int) $subject['created_by'] === $user->getId();
    }
}
