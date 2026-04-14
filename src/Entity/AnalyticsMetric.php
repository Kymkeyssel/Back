<?php

namespace App\Entity;

use App\Repository\AnalyticsMetricRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AnalyticsMetricRepository::class)]
#[ORM\Table(name: 'analytics_metrics')]
#[ORM\HasLifecycleCallbacks]
class AnalyticsMetric
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: AnalyticsDashboard::class, inversedBy: 'metrics')]
    #[ORM\JoinColumn(nullable: false)]
    private ?AnalyticsDashboard $dashboard = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Metric name is required.')]
    private ?string $name = null;

    #[ORM\Column(length: 50)]
    #[Assert\Choice(choices: ['revenue', 'bookings', 'trips', 'users', 'occupancy', 'rating', 'custom'])]
    private ?string $type = null;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2, nullable: true)]
    private ?string $value = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $data = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $unit = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $period = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $startDate = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $endDate = null;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2, nullable: true)]
    private ?string $previousValue = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, nullable: true)]
    private ?string $changePercentage = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $calculatedAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->calculatedAt = new \DateTimeImmutable();
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        // No updatedAt field needed for immutable metrics
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDashboard(): ?AnalyticsDashboard
    {
        return $this->dashboard;
    }

    public function setDashboard(?AnalyticsDashboard $dashboard): static
    {
        $this->dashboard = $dashboard;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
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

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): static
    {
        $this->value = $value;
        return $this;
    }

    public function getData(): ?array
    {
        return $this->data;
    }

    public function setData(?array $data): static
    {
        $this->data = $data;
        return $this;
    }

    public function getUnit(): ?string
    {
        return $this->unit;
    }

    public function setUnit(?string $unit): static
    {
        $this->unit = $unit;
        return $this;
    }

    public function getPeriod(): ?string
    {
        return $this->period;
    }

    public function setPeriod(?string $period): static
    {
        $this->period = $period;
        return $this;
    }

    public function getStartDate(): ?\DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTimeImmutable $startDate): static
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getEndDate(): ?\DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeImmutable $endDate): static
    {
        $this->endDate = $endDate;
        return $this;
    }

    public function getPreviousValue(): ?string
    {
        return $this->previousValue;
    }

    public function setPreviousValue(?string $previousValue): static
    {
        $this->previousValue = $previousValue;
        return $this;
    }

    public function getChangePercentage(): ?string
    {
        return $this->changePercentage;
    }

    public function setChangePercentage(?string $changePercentage): static
    {
        $this->changePercentage = $changePercentage;
        return $this;
    }

    public function getCalculatedAt(): ?\DateTimeImmutable
    {
        return $this->calculatedAt;
    }

    public function setCalculatedAt(\DateTimeImmutable $calculatedAt): static
    {
        $this->calculatedAt = $calculatedAt;
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
     * Calculate change percentage
     */
    public function calculateChangePercentage(): void
    {
        if ($this->previousValue && $this->value && $this->previousValue != 0) {
            $change = ((float)$this->value - (float)$this->previousValue) / (float)$this->previousValue * 100;
            $this->changePercentage = (string) round($change, 2);
        }
    }
}
