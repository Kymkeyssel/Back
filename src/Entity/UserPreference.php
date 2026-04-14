<?php

namespace App\Entity;

use App\Repository\UserPreferenceRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserPreferenceRepository::class)]
#[ORM\Table(name: 'user_preferences')]
#[ORM\HasLifecycleCallbacks]
class UserPreference
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, unique: true)]
    private ?User $user = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $preferredRoutes = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $preferredAgencies = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $preferredVehicleTypes = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $preferredTimeSlots = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $preferredAmenities = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $budgetRange = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $prefersEcoFriendly = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $prefersExpress = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $notificationPreferences = null;

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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getPreferredRoutes(): ?array
    {
        return $this->preferredRoutes;
    }

    public function setPreferredRoutes(?array $preferredRoutes): static
    {
        $this->preferredRoutes = $preferredRoutes;
        return $this;
    }

    public function getPreferredAgencies(): ?array
    {
        return $this->preferredAgencies;
    }

    public function setPreferredAgencies(?array $preferredAgencies): static
    {
        $this->preferredAgencies = $preferredAgencies;
        return $this;
    }

    public function getPreferredVehicleTypes(): ?array
    {
        return $this->preferredVehicleTypes;
    }

    public function setPreferredVehicleTypes(?array $preferredVehicleTypes): static
    {
        $this->preferredVehicleTypes = $preferredVehicleTypes;
        return $this;
    }

    public function getPreferredTimeSlots(): ?array
    {
        return $this->preferredTimeSlots;
    }

    public function setPreferredTimeSlots(?array $preferredTimeSlots): static
    {
        $this->preferredTimeSlots = $preferredTimeSlots;
        return $this;
    }

    public function getPreferredAmenities(): ?array
    {
        return $this->preferredAmenities;
    }

    public function setPreferredAmenities(?array $preferredAmenities): static
    {
        $this->preferredAmenities = $preferredAmenities;
        return $this;
    }

    public function getBudgetRange(): ?string
    {
        return $this->budgetRange;
    }

    public function setBudgetRange(?string $budgetRange): static
    {
        $this->budgetRange = $budgetRange;
        return $this;
    }

    public function isPrefersEcoFriendly(): ?bool
    {
        return $this->prefersEcoFriendly;
    }

    public function setPrefersEcoFriendly(?bool $prefersEcoFriendly): static
    {
        $this->prefersEcoFriendly = $prefersEcoFriendly;
        return $this;
    }

    public function isPrefersExpress(): ?bool
    {
        return $this->prefersExpress;
    }

    public function setPrefersExpress(?bool $prefersExpress): static
    {
        $this->prefersExpress = $prefersExpress;
        return $this;
    }

    public function getNotificationPreferences(): ?array
    {
        return $this->notificationPreferences;
    }

    public function setNotificationPreferences(?array $notificationPreferences): static
    {
        $this->notificationPreferences = $notificationPreferences;
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
