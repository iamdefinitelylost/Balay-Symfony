<?php

namespace App\Entity;

use App\Repository\ListingImageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ListingImageRepository::class)]
#[ORM\Table(name: 'listing_images')]
class ListingImage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Listing::class)]
    #[ORM\JoinColumn(name: 'listing_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Listing $listing;

    #[ORM\Column(length: 80)]
    private string $filename;

    #[ORM\Column(name: 'sort_order', type: Types::INTEGER)]
    private int $sortOrder;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(Listing $listing, string $filename, int $sortOrder = 0)
    {
        $this->listing = $listing;
        $this->filename = $filename;
        $this->sortOrder = $sortOrder;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getListing(): Listing
    {
        return $this->listing;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }
}
