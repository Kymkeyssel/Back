<?php

namespace App\Entity;

use App\Repository\MultiModalTripRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MultiModalTripRepository::class)]
#[ORM\Table(name: 'multi_modal_trips')]
#[ORM\HasLifecycleCallbacks]
class MultiModalTrip
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

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
    private ?string $totalPrice = null;

    #[ORM\Column(nullable: true)]
    private ?int $totalDuration = null;

    #[ORM\Column(nullable: true)]
    private ?float $totalDistance = null;

    #[ORM\Column(length: 50)]
    #[Assert\Choice(choices: ['planned', 'booked', 'in_progress', 'completed', 'cancelled'], message: 'Invalid status.')]
    private ?string $status = 'planned';

    #[ORM\OneToMany(targetEntity: MultiModalTripSegment::class, mappedBy: 'multiModalTrip', cascade: ['persist', 'remove'])]
    private Collection $segments;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $preferences = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->segments = new ArrayCollection();
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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
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

    public function getTotalPrice(): ?string
    {
        return $this->totalPrice;
    }

    public function setTotalPrice(?string $totalPrice): static
    {
        $this->totalPrice = $totalPrice;
        return $this;
    }

    public function getTotalDuration(): ?int
    {
        return $this->totalDuration;
    }

    public function setTotalDuration(?int $totalDuration): static
    {
        $this->totalDuration = $totalDuration;
        return $this;
    }

    public function getTotalDistance(): ?float
    {
        return $this->totalDistance;
    }

    public function setTotalDistance(?float $totalDistance): static
    {
        $this->totalDistance = $totalDistance;
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

    /**
     * @return Collection<int, MultiModalTripSegment>
     */
    public function getSegments(): Collection
    {
        return $this->segments;
    }

    public function addSegment(MultiModalTripSegment $segment): static
    {
        if (!$this->segments->contains($segment)) {
            $this->segments->add($segment);
            $segment->setMultiModalTrip($this);
        }

        return $this;
    }

    public function removeSegment(MultiModalTripSegment $segment): static
    {
        if ($this->segments->removeElement($segment)) {
            // set the owning side to null (unless already changed)
            if ($segment->getMultiModalTrip() === $this) {
                $segment->setMultiModalTrip(null);
            }
        }

        return $this;
    }

    public function getPreferences(): ?array
    {
        return $this->preferences;
    }

    public function setPreferences(?array $preferences): static
    {
        $this->preferences = $preferences;
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

    /**
     * Calculate total price from segments
     */
    public function calculateTotalPrice(): void
    {
        $total = 0;
        foreach ($this->segments as $segment) {
            if ($segment->getTrip()) {
                $total += (float) $segment->getTrip()->getPrice();
            }
        }
        $this->totalPrice = (string) $total;
    }

    /**
     * Calculate total duration from segments
     */
    public function calculateTotalDuration(): void
    {
        $total = 0;
        foreach ($this->segments as $segment) {
            if ($segment->getTrip() && $segment->getTrip()->getDuration()) {
                $total += $segment->getTrip()->getDuration();
            }
            // Add transfer time between segments
            if ($segment->getTransferDuration()) {
                $total += $segment->getTransferDuration();
            }
        }
        $this->totalDuration = $total;
    }

    /**
     * Calculate total distance from segments
     */
    public function calculateTotalDistance(): void
    {
        $total = 0;
        foreach ($this->segments as $segment) {
            if ($segment->getTrip() && $segment->getTrip()->getDistance()) {
                $total += $segment->getTrip()->getDistance();
            }
        }
        $this->totalDistance = $total;
    }
}
