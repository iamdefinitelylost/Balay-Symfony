<?php

namespace App\Entity;

use App\Repository\GuestReviewRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** The owner of a stay reviews the guest who stayed there. One review per booking. */
#[ORM\Entity(repositoryClass: GuestReviewRepository::class)]
#[ORM\Table(name: 'guest_reviews')]
class GuestReview
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Booking::class)]
    #[ORM\JoinColumn(name: 'booking_id', referencedColumnName: 'id', nullable: false, unique: true, onDelete: 'CASCADE')]
    private Booking $booking;

    #[ORM\ManyToOne(targetEntity: Listing::class)]
    #[ORM\JoinColumn(name: 'listing_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Listing $listing;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'guest_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $guest;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'reviewer_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $reviewer;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $rating = 5;

    #[ORM\Column(type: Types::TEXT)]
    private string $comment = '';

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(Booking $booking, ?User $reviewer)
    {
        $this->booking = $booking;
        $this->listing = $booking->getListing();
        $this->guest = $booking->getUser();
        $this->reviewer = $reviewer;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBooking(): Booking
    {
        return $this->booking;
    }

    public function getGuest(): User
    {
        return $this->guest;
    }

    public function getReviewer(): ?User
    {
        return $this->reviewer;
    }

    public function setReviewer(?User $reviewer): static
    {
        $this->reviewer = $reviewer;

        return $this;
    }

    public function getRating(): int
    {
        return $this->rating;
    }

    public function setRating(int $rating): static
    {
        $this->rating = $rating;

        return $this;
    }

    public function getComment(): string
    {
        return $this->comment;
    }

    public function setComment(string $comment): static
    {
        $this->comment = $comment;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
