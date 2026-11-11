<?php

namespace App\Repository;

use App\Entity\Listing;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Listing> */
class ListingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Listing::class);
    }

    /**
     * Search and filter records. $ownerId limits the result to one creator ("only mine").
     *
     * @return Listing[]
     */
    public function search(string $q, string $type, ?int $ownerId): array
    {
        $qb = $this->createQueryBuilder('l')
            ->leftJoin('l.createdBy', 'u')->addSelect('u')
            ->orderBy('l.id', 'DESC');
        if ($q !== '') {
            $qb->andWhere('l.title LIKE :q OR l.city LIKE :q OR l.host LIKE :q')->setParameter('q', '%' . $q . '%');
        }
        if (in_array($type, Listing::TYPES, true)) {
            $qb->andWhere('l.type = :type')->setParameter('type', $type);
        }
        if ($ownerId !== null) {
            $qb->andWhere('u.id = :owner')->setParameter('owner', $ownerId);
        }

        return $qb->getQuery()->getResult();
    }

    /** @return string[] */
    public function distinctCities(): array
    {
        return array_column(
            $this->createQueryBuilder('l')->select('DISTINCT l.city AS city')->orderBy('l.city')->getQuery()->getArrayResult(),
            'city'
        );
    }
}
