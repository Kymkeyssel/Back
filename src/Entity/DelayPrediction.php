<?php

namespace App\Entity;

use App\Repository\DelayPredictionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DelayPredictionRepository::class)]
#[ORM\Table(name: 'delay_predictions')]
#[ORM\HasLifecycleCallbacks]
class DelayPrediction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Trip::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Trip $trip = null;

    #[ORM\Column(type: 'integer')]
    #[Assert\NotBlank(message: 'Predicted delay is required.')]
    private ?int $predictedDelayMinutes = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    #[Assert\NotBlank(message: 'Confidence score is required.')]
    #[Assert\Range(min: 0, max: 100, minMessage: 'Confidence must be at least {{ min }}.', maxMessage: 'Confidence cannot be more than {{ max }}.')]
    private ?string $confidenceScore = null;

    #[ORM\Column(length: 50)]
    #[Assert\Choice(choices: ['low', 'medium', 'high', 'critical'])]
    private ?string $riskLevel = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $factors = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $reasoning = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $predictedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $actualArrivalTime = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $actualDelayMinutes = null;

    #[ORM\Column]
    private ?bool $isAccurate = false;

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

    public function getTrip(): ?Trip
    {
        return $this->trip;
    }

    public function setTrip(?Trip $trip): static
    {
        $this->trip = $trip;
        return $this;
    }

    public function getPredictedDelayMinutes(): ?int
    {
        return $this->predictedDelayMinutes;
    }

    public function setPredictedDelayMinutes(int $predictedDelayMinutes): static
    {
        $this->predictedDelayMinutes = $predictedDelayMinutes;
        return $this;
    }

    public function getConfidenceScore(): ?string
    {
        return $this->confidenceScore;
    }

    public function setConfidenceScore(string $confidenceScore): static
    {
        $this->confidenceScore = $confidenceScore;
        return $this;
    }

    public function getRiskLevel(): ?string
    {
        return $this->riskLevel;
    }

    public function setRiskLevel(string $riskLevel): static
    {
        $this->riskLevel = $riskLevel;
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

    public function getReasoning(): ?string
    {
        return $this->reasoning;
    }

    public function setReasoning(?string $reasoning): static
    {
        $this->reasoning = $reasoning;
        return $this;
    }

    public function getPredictedAt(): ?\DateTimeImmutable
    {
        return $this->predictedAt;
    }

    public function setPredictedAt(?\DateTimeImmutable $predictedAt): static
    {
        $this->predictedAt = $predictedAt;
        return $this;
    }

    public function getActualArrivalTime(): ?\DateTimeImmutable
    {
        return $this->actualArrivalTime;
    }

    public function setActualArrivalTime(?\DateTimeImmutable $actualArrivalTime): static
    {
        $this->actualArrivalTime = $actualArrivalTime;
        return $this;
    }

    public function getActualDelayMinutes(): ?int
    {
        return $this->actualDelayMinutes;
    }

    public function setActualDelayMinutes(?int $actualDelayMinutes): static
    {
        $this->actualDelayMinutes = $actualDelayMinutes;
        return $this;
    }

    public function isAccurate(): ?bool
    {
        return $this->isAccurate;
    }

    public function setIsAccurate(bool $isAccurate): static
    {
        $this->isAccurate = $isAccurate;
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
     * Calculate prediction accuracy
     */
    public function calculateAccuracy(): void
    {
        if ($this->actualDelayMinutes !== null) {
            $difference = abs($this->predictedDelayMinutes - $this->actualDelayMinutes);
            $this->isAccurate = $difference <= 10; // Within 10 minutes is considered accurate
        }
    }
}
