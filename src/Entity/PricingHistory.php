<?php

namespace App\Entity;

use App\Repository\PricingHistoryRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PricingHistoryRepository::class)]
#[ORM\Table(name: 'pricing_history')]
#[ORM\HasLifecycleCallbacks]
class PricingHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Trip::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Trip $trip = null;

    #[ORM\ManyToOne(targetEntity: PricingRule::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?PricingRule $pricingRule = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\NotBlank(message: 'Base price is required.')]
    private ?string $basePrice = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\NotBlank(message: 'Final price is required.')]
    private ?string $finalPrice = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private ?string $multiplier = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $factors = null;

    #[ORM\Column(length: 50)]
    #[Assert\Choice(choices: ['calculated', 'applied', 'reverted'])]
    private ?string $status = 'calculated';

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $appliedAt = null;

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

    public function getTrip(): ?Trip
    {
        return $this->trip;
    }

    public function setTrip(?Trip $trip): static
    {
        $this->trip = $trip;
        return $this;
    }

    public function getPricingRule(): ?PricingRule
    {
        return $this->pricingRule;
    }

    public function setPricingRule(?PricingRule $pricingRule): static
    {
        $this->pricingRule = $pricingRule;
        return $this;
    }

    public function getBasePrice(): ?string
    {
        return $this->basePrice;
    }

    public function setBasePrice(string $basePrice): static
    {
        $this->basePrice = $basePrice;
        return $this;
    }

    public function getFinalPrice(): ?string
    {
        return $this->finalPrice;
    }

    public function setFinalPrice(string $finalPrice): static
    {
        $this->finalPrice = $finalPrice;
        return $this;
    }

    public function getMultiplier(): ?string
    {
        return $this->multiplier;
    }

    public function setMultiplier(string $multiplier): static
    {
        $this->multiplier = $multiplier;
        return $this;
    }

    public function getFactors(): ?array
    {
        return $this->factors;
    }

    public function setFactors(?array $factors): static
    {
        $this->factors = $factors;
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

    public function getAppliedAt(): ?\DateTimeImmutable
    {
        return $this->appliedAt;
    }

    public function setAppliedAt(?\DateTimeImmutable $appliedAt): static
    {
        $this->appliedAt = $appliedAt;
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
     * Get price change percentage
     */
    public function getPriceChangePercentage(): float
    {
        $basePrice = (float) $this->basePrice;
        $finalPrice = (float) $this->finalPrice;

        if ($basePrice == 0) {
            return 0;
        }

        return (($finalPrice - $basePrice) / $basePrice) * 100;
    }
}
