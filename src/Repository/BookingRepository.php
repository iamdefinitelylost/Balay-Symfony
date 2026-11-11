<?php

namespace App\Repository;

use App\Entity\Booking;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Booking> */
class BookingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Booking::class);
    }

    /** @return Booking[] */
    public function findAllWithListing(): array
    {
        return $this->createQueryBuilder('b')
            ->join('b.listing', 'l')->addSelect('l')
            ->orderBy('b.createdAt', 'DESC')->addOrderBy('b.id', 'DESC')
            ->getQuery()->getResult();
    }

    public function revenue(): int
    {
        return (int) $this->createQueryBuilder('b')
            ->select('COALESCE(SUM(b.total), 0)')
            ->where("b.status = 'confirmed'")
            ->getQuery()->getSingleScalarResult();
    }
}
