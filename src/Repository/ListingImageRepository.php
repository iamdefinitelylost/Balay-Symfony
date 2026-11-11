<?php

namespace App\Repository;

use App\Entity\Listing;
use App\Entity\ListingImage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ListingImage> */
class ListingImageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ListingImage::class);
    }

    /** @return ListingImage[] */
    public function forListing(Listing $listing): array
    {
        return $this->findBy(['listing' => $listing], ['sortOrder' => 'ASC', 'id' => 'ASC']);
    }
}
