<?php

namespace App\Entity;

use App\Repository\RecommendationHistoryRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RecommendationHistoryRepository::class)]
#[ORM\Table(name: 'recommendation_history')]
#[ORM\HasLifecycleCallbacks]
class RecommendationHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Trip::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Trip $trip = null;

    #[ORM\Column(length: 50)]
    #[Assert\Choice(choices: ['route', 'agency', 'trip', 'offer', 'destination'])]
    private ?string $type = null;

    #[ORM\Column(type: 'json')]
    private array $recommendationData = [];

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    #[Assert\Range(min: 0, max: 100, minMessage: 'Score must be at least {{ min }}.', maxMessage: 'Score cannot be more than {{ max }}.')]
    private ?string $relevanceScore = null;

    #[ORM\Column(type: 'boolean')]
    private ?bool $wasClicked = false;

    #[ORM\Column(type: 'boolean')]
    private ?bool $wasBooked = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $clickedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $bookedAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        // No updatedAt field needed for immutable history
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

    public function getTrip(): ?Trip
    {
        return $this->trip;
    }

    public function setTrip(?Trip $trip): static
    {
        $this->trip = $trip;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getRecommendationData(): array
    {
        return $this->recommendationData;
    }

    public function setRecommendationData(array $recommendationData): static
    {
        $this->recommendationData = $recommendationData;
        return $this;
    }

    public function getRelevanceScore(): ?string
    {
        return $this->relevanceScore;
    }

    public function setRelevanceScore(string $relevanceScore): static
    {
        $this->relevanceScore = $relevanceScore;
        return $this;
    }

    public function wasClicked(): ?bool
    {
        return $this->wasClicked;
    }

    public function setWasClicked(bool $wasClicked): static
    {
        $this->wasClicked = $wasClicked;
        return $this;
    }

    public function wasBooked(): ?bool
    {
        return $this->wasBooked;
    }

    public function setWasBooked(bool $wasBooked): static
    {
        $this->wasBooked = $wasBooked;
        return $this;
    }

    public function getClickedAt(): ?\DateTimeImmutable
    {
        return $this->clickedAt;
    }

    public function setClickedAt(?\DateTimeImmutable $clickedAt): static
    {
        $this->clickedAt = $clickedAt;
        return $this;
    }

    public function getBookedAt(): ?\DateTimeImmutable
    {
        return $this->bookedAt;
    }

    public function setBookedAt(?\DateTimeImmutable $bookedAt): static
    {
        $this->bookedAt = $bookedAt;
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

    /**
     * Mark recommendation as clicked
     */
    public function markAsClicked(): void
    {
        $this->wasClicked = true;
        $this->clickedAt = new \DateTimeImmutable();
    }

    /**
     * Mark recommendation as booked
     */
    public function markAsBooked(): void
    {
        $this->wasBooked = true;
        $this->bookedAt = new \DateTimeImmutable();
    }
}
