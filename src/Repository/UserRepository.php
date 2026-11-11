<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<User> */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findOneByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => strtolower(trim($email))]);
    }

    /** Is this email used by another account? (pass the account's own id when editing) */
    public function emailTaken(string $email, ?int $exceptId = null): bool
    {
        $qb = $this->createQueryBuilder('u')->select('COUNT(u.id)')->where('u.email = :email')->setParameter('email', $email);
        if ($exceptId !== null) {
            $qb->andWhere('u.id <> :id')->setParameter('id', $exceptId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /** @return User[] newest first, optionally filtered */
    public function search(string $q, string $role, string $status): array
    {
        $qb = $this->createQueryBuilder('u')->orderBy('u.createdAt', 'DESC')->addOrderBy('u.id', 'DESC');
        if ($q !== '') {
            $qb->andWhere('u.name LIKE :q OR u.email LIKE :q')->setParameter('q', '%' . $q . '%');
        }
        if (in_array($role, User::ROLES, true)) {
            $qb->andWhere('u.role = :role')->setParameter('role', $role);
        }
        if (in_array($status, User::STATUSES, true)) {
            $qb->andWhere('u.status = :status')->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }
}
