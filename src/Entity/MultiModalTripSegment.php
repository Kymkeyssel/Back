<?php

namespace App\Entity;

use App\Repository\MultiModalTripSegmentRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MultiModalTripSegmentRepository::class)]
#[ORM\Table(name: 'multi_modal_trip_segments')]
#[ORM\HasLifecycleCallbacks]
class MultiModalTripSegment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: MultiModalTrip::class, inversedBy: 'segments')]
    #[ORM\JoinColumn(nullable: false)]
    private ?MultiModalTrip $multiModalTrip = null;

    #[ORM\ManyToOne(targetEntity: Trip::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Trip $trip = null;

    #[ORM\ManyToOne(targetEntity: TransportMode::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?TransportMode $transportMode = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: 'Segment order is required.')]
    #[Assert\Positive(message: 'Segment order must be positive.')]
    private ?int $segmentOrder = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Departure city is required.')]
    private ?string $departureCity = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Arrival city is required.')]
    private ?string $arrivalCity = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: 'Departure time is required.')]
    private ?\DateTimeImmutable $departureTime = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $arrivalTime = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $price = null;

    #[ORM\Column(nullable: true)]
    private ?int $duration = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $distance = null;

    #[ORM\Column(nullable: true)]
    private ?int $transferDuration = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $transferNotes = null;

    #[ORM\Column(length: 50)]
    #[Assert\Choice(choices: ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'], message: 'Invalid status.')]
    private ?string $status = 'pending';

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
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

    public function getMultiModalTrip(): ?MultiModalTrip
    {
        return $this->multiModalTrip;
    }

    public function setMultiModalTrip(?MultiModalTrip $multiModalTrip): static
    {
        $this->multiModalTrip = $multiModalTrip;
        return $this;
    }

    public function getTrip(): ?Trip
    {
        return $this->trip;
    }

    public function setTrip(?Trip $trip): static
    {
        $this->trip = $trip;
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

    public function getSegmentOrder(): ?int
    {
        return $this->segmentOrder;
    }

    public function setSegmentOrder(int $segmentOrder): static
    {
        $this->segmentOrder = $segmentOrder;
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

    public function setPrice(?string $price): static
    {
        $this->price = $price;
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

    public function getDistance(): ?float
    {
        return $this->distance;
    }

    public function setDistance(?float $distance): static
    {
        $this->distance = $distance;
        return $this;
    }

    public function getTransferDuration(): ?int
    {
        return $this->transferDuration;
    }

    public function setTransferDuration(?int $transferDuration): static
    {
        $this->transferDuration = $transferDuration;
        return $this;
    }

    public function getTransferNotes(): ?string
    {
        return $this->transferNotes;
    }

    public function setTransferNotes(?string $transferNotes): static
    {
        $this->transferNotes = $transferNotes;
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
