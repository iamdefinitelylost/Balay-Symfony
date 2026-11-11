<?php

namespace App\Entity;

use App\Repository\BookingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Created by the public booking API (with a row lock); read and cancelled from the admin area. */
#[ORM\Entity(repositoryClass: BookingRepository::class)]
#[ORM\Table(name: 'bookings')]
class Booking
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Listing::class)]
    #[ORM\JoinColumn(name: 'listing_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Listing $listing;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 20)]
    private string $status = 'confirmed';

    #[ORM\Column(name: 'guest_name', length: 80)]
    private string $guestName = '';

    #[ORM\Column(name: 'guest_email', length: 120)]
    private string $guestEmail = '';

    #[ORM\Column(name: 'check_in', type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $checkIn;

    #[ORM\Column(name: 'check_out', type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $checkOut;

    #[ORM\Column(type: Types::INTEGER)]
    private int $guests = 1;

    #[ORM\Column(type: Types::INTEGER)]
    private int $total = 0;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getListing(): Listing
    {
        return $this->listing;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getGuestName(): string
    {
        return $this->guestName;
    }

    public function getGuestEmail(): string
    {
        return $this->guestEmail;
    }

    public function getCheckIn(): \DateTimeImmutable
    {
        return $this->checkIn;
    }

    public function getCheckOut(): \DateTimeImmutable
    {
        return $this->checkOut;
    }

    public function getGuests(): int
    {
        return $this->guests;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
