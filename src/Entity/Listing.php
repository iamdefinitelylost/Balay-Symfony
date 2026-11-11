<?php

namespace App\Entity;

use App\Repository\ListingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** A stay. In the admin/staff area these are the "records". */
#[ORM\Entity(repositoryClass: ListingRepository::class)]
#[ORM\Table(name: 'listings')]
class Listing
{
    public const TYPES = ['Beach', 'Mountain', 'City'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $title = '';

    #[ORM\Column(length: 60)]
    private string $city = '';

    #[ORM\Column(length: 20)]
    private string $type = 'Beach';

    #[ORM\Column(type: Types::INTEGER)]
    private int $price = 0;

    #[ORM\Column(name: 'max_guests', type: Types::INTEGER)]
    private int $maxGuests = 1;

    #[ORM\Column(type: Types::DECIMAL, precision: 2, scale: 1)]
    private string $rating = '0.0';

    #[ORM\Column(type: Types::INTEGER)]
    private int $reviews = 0;

    #[ORM\Column(length: 60)]
    private string $host = '';

    #[ORM\Column(name: 'is_new', type: Types::BOOLEAN)]
    private bool $isNew = false;

    #[ORM\Column(length: 255)]
    private string $amenities = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $description = '';

    #[ORM\Column(length: 7)]
    private string $color1 = '#1F8A8A';

    #[ORM\Column(length: 7)]
    private string $color2 = '#F2A93B';

    /** Who created this record. Staff may only edit/delete records where this is themselves. */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    /** Copies the editable fields from a validated form array (see toFormArray()). */
    public function fill(array $f): static
    {
        $this->title = $f['title'];
        $this->city = $f['city'];
        $this->type = $f['type'];
        $this->price = (int) $f['price'];
        $this->maxGuests = (int) $f['max_guests'];
        $this->host = $f['host'];
        $this->isNew = (bool) $f['is_new'];
        $this->amenities = $f['amenities'];
        $this->description = $f['description'];
        $this->color1 = $f['color1'];
        $this->color2 = $f['color2'];

        return $this;
    }

    public function toFormArray(): array
    {
        return [
            'title' => $this->title, 'city' => $this->city, 'type' => $this->type,
            'price' => $this->price, 'max_guests' => $this->maxGuests, 'host' => $this->host,
            'is_new' => $this->isNew ? 1 : 0, 'amenities' => $this->amenities, 'description' => $this->description,
            'color1' => $this->color1, 'color2' => $this->color2,
        ];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getPrice(): int
    {
        return $this->price;
    }

    public function getMaxGuests(): int
    {
        return $this->maxGuests;
    }

    public function getRating(): string
    {
        return $this->rating;
    }

    public function getReviews(): int
    {
        return $this->reviews;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function isNew(): bool
    {
        return $this->isNew;
    }

    public function getAmenities(): string
    {
        return $this->amenities;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $user): static
    {
        $this->createdBy = $user;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
