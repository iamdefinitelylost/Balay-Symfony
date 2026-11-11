<?php

namespace App\Repository;

use App\Entity\Booking;
use App\Entity\GuestReview;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<GuestReview> */
class GuestReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GuestReview::class);
    }

    public function findForBooking(Booking $booking): ?GuestReview
    {
        return $this->findOneBy(['booking' => $booking]);
    }

    /**
     * @param Booking[] $bookings
     * @return array<int, GuestReview> the reviews of those bookings, keyed by booking id
     */
    public function byBookings(array $bookings): array
    {
        if (!$bookings) {
            return [];
        }
        $map = [];
        foreach ($this->findBy(['booking' => $bookings]) as $review) {
            $map[$review->getBooking()->getId()] = $review;
        }

        return $map;
    }

    /**
     * Average rating hosts gave each guest.
     *
     * @param int[] $guestIds
     * @return array<int, array{avg: float, count: int}> keyed by guest id
     */
    public function summariesForGuests(array $guestIds): array
    {
        if (!$guestIds) {
            return [];
        }
        $rows = $this->createQueryBuilder('r')
            ->select('IDENTITY(r.guest) AS gid, AVG(r.rating) AS avg_rating, COUNT(r.id) AS cnt')
            ->where('IDENTITY(r.guest) IN (:ids)')->setParameter('ids', array_values($guestIds))
            ->groupBy('r.guest')
            ->getQuery()->getArrayResult();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['gid']] = ['avg' => round((float) $row['avg_rating'], 1), 'count' => (int) $row['cnt']];
        }

        return $out;
    }
}
