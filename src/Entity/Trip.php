<?php

namespace App\Entity;

use App\Domain\TransportScope;
use App\Repository\TripRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: TripRepository::class)]
#[ORM\Table(name: 'trips')]
#[ORM\HasLifecycleCallbacks]
class Trip
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Agency::class, inversedBy: 'trips')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Agency $agency = null;

    #[ORM\ManyToOne(targetEntity: Vehicle::class, inversedBy: 'trips')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Vehicle $vehicle = null;

    #[ORM\ManyToOne(targetEntity: TransportMode::class, inversedBy: 'trips')]
    #[ORM\JoinColumn(nullable: false)]
    private ?TransportMode $transportMode = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Departure city is required.')]
    private ?string $departureCity = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Arrival city is required.')]
    private ?string $arrivalCity = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Departure address is required.')]
    private ?string $departureAddress = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Arrival address is required.')]
    private ?string $arrivalAddress = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: 'Departure time is required.')]
    private ?\DateTimeImmutable $departureTime = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $arrivalTime = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank(message: 'Price is required.')]
    #[Assert\Positive(message: 'Price must be positive.')]
    private ?string $price = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: 'Available seats is required.')]
    #[Assert\PositiveOrZero(message: 'Available seats must be positive or zero.')]
    private ?int $availableSeats = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: 'Total seats is required.')]
    #[Assert\Positive(message: 'Total seats must be positive.')]
    private ?int $totalSeats = null;

    #[ORM\Column(length: 50)]
    #[Assert\Choice(choices: ['scheduled', 'in_progress', 'completed', 'cancelled'], message: 'Invalid status.')]
    private ?string $status = 'scheduled';

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $boardingPlatform = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $distance = null;

    #[ORM\Column(nullable: true)]
    private ?int $duration = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $departureLatitude = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $departureLongitude = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $arrivalLatitude = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $arrivalLongitude = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $currentLatitude = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $currentLongitude = null;

    #[ORM\OneToMany(targetEntity: Booking::class, mappedBy: 'trip')]
    private Collection $bookings;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[Assert\Callback]
    public function validateVehicleMatchesOfferType(ExecutionContextInterface $context): void
    {
        $mode = $this->transportMode;
        $vehicle = $this->vehicle;
        if (null === $mode || null === $vehicle || null === $vehicle->getType() || null === $mode->getCode()) {
            return;
        }

        $code = $mode->getCode();
        $vehicleType = $vehicle->getType();

        if (TransportScope::CARPOOL === $code && TransportScope::CARPOOL_VEHICLE_TYPE !== $vehicleType) {
            $context->buildViolation('Carpool offers must use a passenger car (vehicle type "car").')
                ->atPath('vehicle')
                ->addViolation();
        }

        if (TransportScope::INTERCITY_BUS === $code && !in_array($vehicleType, TransportScope::INTERCITY_VEHICLE_TYPES, true)) {
            $context->buildViolation('Scheduled intercity lines must use a bus or minibus.')
                ->atPath('vehicle')
                ->addViolation();
        }
    }

    public function __construct()
    {
        $this->bookings = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAgency(): ?Agency
    {
        return $this->agency;
    }

    public function setAgency(?Agency $agency): static
    {
        $this->agency = $agency;
        return $this;
    }

    public function getVehicle(): ?Vehicle
    {
        return $this->vehicle;
    }

    public function setVehicle(?Vehicle $vehicle): static
    {
        $this->vehicle = $vehicle;
        return $this;
    }

    public function getTransportMode(): ?TransportMode
    {
        return $this->transportMode;
    }

    public function setTransportMode(?TransportMode $transportMode): static
    {
        $this->transportMode = $transportMode;
        return $this;
    }

    public function getDepartureCity(): ?string
    {
        return $this->departureCity;
    }

    public function setDepartureCity(string $departureCity): static
    {
        $this->departureCity = $departureCity;
        return $this;
    }

    public function getArrivalCity(): ?string
    {
        return $this->arrivalCity;
    }

    public function setArrivalCity(string $arrivalCity): static
    {
        $this->arrivalCity = $arrivalCity;
        return $this;
    }

    public function getDepartureAddress(): ?string
    {
        return $this->departureAddress;
    }

    public function setDepartureAddress(string $departureAddress): static
    {
        $this->departureAddress = $departureAddress;
        return $this;
    }

    public function getArrivalAddress(): ?string
    {
        return $this->arrivalAddress;
    }

    public function setArrivalAddress(string $arrivalAddress): static
    {
        $this->arrivalAddress = $arrivalAddress;
        return $this;
    }

    public function getDepartureTime(): ?\DateTimeImmutable
    {
        return $this->departureTime;
    }

    public function setDepartureTime(\DateTimeImmutable $departureTime): static
    {
        $this->departureTime = $departureTime;
        return $this;
    }

    public function getArrivalTime(): ?\DateTimeImmutable
    {
        return $this->arrivalTime;
    }

    public function setArrivalTime(?\DateTimeImmutable $arrivalTime): static
    {
        $this->arrivalTime = $arrivalTime;
        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(string $price): static
    {
        $this->price = $price;
        return $this;
    }

    public function getAvailableSeats(): ?int
    {
        return $this->availableSeats;
    }

    public function setAvailableSeats(int $availableSeats): static
    {
        $this->availableSeats = $availableSeats;
        return $this;
    }

    public function getTotalSeats(): ?int
    {
        return $this->totalSeats;
    }

    public function setTotalSeats(int $totalSeats): static
    {
        $this->totalSeats = $totalSeats;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getBoardingPlatform(): ?string
    {
        return $this->boardingPlatform;
    }

    public function setBoardingPlatform(?string $boardingPlatform): static
    {
        $this->boardingPlatform = $boardingPlatform;
        return $this;
    }

    public function getDistance(): ?float
    {
        return $this->distance;
    }

    public function setDistance(?float $distance): static
    {
        $this->distance = $distance;
        return $this;
    }

    public function getDuration(): ?int
    {
        return $this->duration;
    }

    public function setDuration(?int $duration): static
    {
        $this->duration = $duration;
        return $this;
    }

    public function getDepartureLatitude(): ?float
    {
        return $this->departureLatitude;
    }

    public function setDepartureLatitude(?float $departureLatitude): static
    {
        $this->departureLatitude = $departureLatitude;
        return $this;
    }

    public function getDepartureLongitude(): ?float
    {
        return $this->departureLongitude;
    }

    public function setDepartureLongitude(?float $departureLongitude): static
    {
        $this->departureLongitude = $departureLongitude;
        return $this;
    }

    public function getArrivalLatitude(): ?float
    {
        return $this->arrivalLatitude;
    }

    public function setArrivalLatitude(?float $arrivalLatitude): static
    {
        $this->arrivalLatitude = $arrivalLatitude;
        return $this;
    }

    public function getArrivalLongitude(): ?float
    {
        return $this->arrivalLongitude;
    }

    public function setArrivalLongitude(?float $arrivalLongitude): static
    {
        $this->arrivalLongitude = $arrivalLongitude;
        return $this;
    }

    public function getCurrentLatitude(): ?float
    {
        return $this->currentLatitude;
    }

    public function setCurrentLatitude(?float $currentLatitude): static
    {
        $this->currentLatitude = $currentLatitude;
        return $this;
    }

    public function getCurrentLongitude(): ?float
    {
        return $this->currentLongitude;
    }

    public function setCurrentLongitude(?float $currentLongitude): static
    {
        $this->currentLongitude = $currentLongitude;
        return $this;
    }

    public function getBookings(): Collection
    {
        return $this->bookings;
    }

    public function addBooking(Booking $booking): static
    {
        if (!$this->bookings->contains($booking)) {
            $this->bookings->add($booking);
            $booking->setTrip($this);
        }
        return $this;
    }

    public function removeBooking(Booking $booking): static
    {
        if ($this->bookings->removeElement($booking)) {
            if ($booking->getTrip() === $this) {
                $booking->setTrip(null);
            }
        }
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
}
