<?php

namespace App\Service;

use App\Entity\Agency;
use App\Entity\PricingHistory;
use App\Entity\PricingRule;
use App\Entity\Trip;
use App\Repository\BookingRepository;
use App\Repository\PricingHistoryRepository;
use App\Repository\PricingRuleRepository;
use Doctrine\ORM\EntityManagerInterface;

class PricingService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PricingRuleRepository $pricingRuleRepository,
        private PricingHistoryRepository $pricingHistoryRepository,
        private BookingRepository $bookingRepository
    ) {
    }

    /**
     * Calculate dynamic price for a trip
     */
    public function calculatePrice(Trip $trip, array $context = []): array
    {
        $basePrice = (float) $trip->getPrice();
        $finalPrice = $basePrice;
        $appliedRules = [];
        $factors = [];

        // Get applicable rules
        $rules = $this->getApplicableRules($trip, $context);

        // Sort by priority (highest first)
        usort($rules, fn(PricingRule $a, PricingRule $b) => $b->getPriority() <=> $a->getPriority());

        // Apply rules
        foreach ($rules as $rule) {
            if ($this->evaluateRule($rule, $trip, $context)) {
                $multiplier = (float) $rule->getMultiplier();
                $finalPrice *= $multiplier;

                $appliedRules[] = [
                    'id' => $rule->getId(),
                    'name' => $rule->getName(),
                    'type' => $rule->getType(),
                    'multiplier' => $multiplier,
                ];

                $factors[$rule->getType()] = [
                    'rule' => $rule->getName(),
                    'multiplier' => $multiplier,
                ];
            }
        }

        // Apply min/max constraints
        $finalPrice = $this->applyConstraints($finalPrice, $rules);

        // Round to 2 decimal places
        $finalPrice = round($finalPrice, 2);

        // Calculate overall multiplier
        $overallMultiplier = $basePrice > 0 ? $finalPrice / $basePrice : 1.0;

        return [
            'base_price' => $basePrice,
            'final_price' => $finalPrice,
            'multiplier' => round($overallMultiplier, 2),
            'applied_rules' => $appliedRules,
            'factors' => $factors,
            'savings' => $basePrice - $finalPrice,
            'savings_percentage' => $basePrice > 0 ? round((($basePrice - $finalPrice) / $basePrice) * 100, 2) : 0,
        ];
    }

    /**
     * Get price for a trip with history
     */
    public function getPriceWithHistory(Trip $trip, array $context = []): array
    {
        $priceCalculation = $this->calculatePrice($trip, $context);

        // Save pricing history
        $pricingHistory = new PricingHistory();
        $pricingHistory->setTrip($trip);
        $pricingHistory->setBasePrice((string) $priceCalculation['base_price']);
        $pricingHistory->setFinalPrice((string) $priceCalculation['final_price']);
        $pricingHistory->setMultiplier((string) $priceCalculation['multiplier']);
        $pricingHistory->setFactors($priceCalculation['factors']);
        $pricingHistory->setStatus('calculated');

        $this->pricingHistoryRepository->save($pricingHistory, true);

        return array_merge($priceCalculation, [
            'history_id' => $pricingHistory->getId(),
        ]);
    }

    /**
     * Apply pricing rule to trip
     */
    public function applyPricingRule(Trip $trip, PricingRule $rule): PricingHistory
    {
        $basePrice = (float) $trip->getPrice();
        $multiplier = (float) $rule->getMultiplier();
        $finalPrice = round($basePrice * $multiplier, 2);

        // Apply constraints
        $finalPrice = $this->applyConstraints($finalPrice, [$rule]);

        $pricingHistory = new PricingHistory();
        $pricingHistory->setTrip($trip);
        $pricingHistory->setPricingRule($rule);
        $pricingHistory->setBasePrice((string) $basePrice);
        $pricingHistory->setFinalPrice((string) $finalPrice);
        $pricingHistory->setMultiplier((string) $multiplier);
        $pricingHistory->setFactors([
            $rule->getType() => [
                'rule' => $rule->getName(),
                'multiplier' => $multiplier,
            ],
        ]);
        $pricingHistory->setStatus('applied');
        $pricingHistory->setAppliedAt(new \DateTimeImmutable());

        $this->pricingHistoryRepository->save($pricingHistory, true);

        return $pricingHistory;
    }

    /**
     * Get demand-based multiplier
     */
    public function getDemandMultiplier(Trip $trip): float
    {
        $totalSeats = $trip->getTotalSeats();
        $availableSeats = $trip->getAvailableSeats();
        $bookedSeats = $totalSeats - $availableSeats;

        if ($totalSeats == 0) {
            return 1.0;
        }

        $occupancyRate = $bookedSeats / $totalSeats;

        // High demand: > 80% occupancy
        if ($occupancyRate > 0.8) {
            return 1.3; // 30% increase
        }

        // Medium demand: 50-80% occupancy
        if ($occupancyRate > 0.5) {
            return 1.15; // 15% increase
        }

        // Low demand: < 30% occupancy
        if ($occupancyRate < 0.3) {
            return 0.9; // 10% discount
        }

        return 1.0; // No change
    }

    /**
     * Get time-based multiplier
     */
    public function getTimeMultiplier(Trip $trip): float
    {
        $departureTime = $trip->getDepartureTime();
        $now = new \DateTimeImmutable();
        $hoursUntilDeparture = ($departureTime->getTimestamp() - $now->getTimestamp()) / 3600;

        // Last minute: < 24 hours
        if ($hoursUntilDeparture < 24) {
            return 1.25; // 25% increase
        }

        // Early bird: > 30 days
        if ($hoursUntilDeparture > 720) {
            return 0.85; // 15% discount
        }

        // Advance booking: 7-30 days
        if ($hoursUntilDeparture > 168) {
            return 0.95; // 5% discount
        }

        return 1.0; // No change
    }

    /**
     * Get loyalty multiplier
     */
    public function getLoyaltyMultiplier(int $userId): float
    {
        $bookingCount = $this->bookingRepository->countByUser($userId);

        // VIP: > 50 bookings
        if ($bookingCount > 50) {
            return 0.8; // 20% discount
        }

        // Gold: 20-50 bookings
        if ($bookingCount > 20) {
            return 0.9; // 10% discount
        }

        // Silver: 10-20 bookings
        if ($bookingCount > 10) {
            return 0.95; // 5% discount
        }

        return 1.0; // No discount
    }

    /**
     * Get applicable rules for a trip
     * @return PricingRule[]
     */
    private function getApplicableRules(Trip $trip, array $context): array
    {
        $agency = $trip->getAgency();
        $rules = $this->pricingRuleRepository->findActiveByAgency($agency);

        // Filter by validity
        return array_filter($rules, fn(PricingRule $rule) => $rule->isValid());
    }

    /**
     * Evaluate if a rule applies to the trip
     */
    private function evaluateRule(PricingRule $rule, Trip $trip, array $context): bool
    {
        $conditions = $rule->getConditions();

        if (empty($conditions)) {
            return true;
        }

        foreach ($conditions as $condition) {
            if (!$this->evaluateCondition($condition, $trip, $context)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Evaluate a single condition
     */
    private function evaluateCondition(array $condition, Trip $trip, array $context): bool
    {
        $type = $condition['type'] ?? null;
        $value = $condition['value'] ?? null;

        switch ($type) {
            case 'departure_city':
                return $trip->getDepartureCity() === $value;

            case 'arrival_city':
                return $trip->getArrivalCity() === $value;

            case 'min_occupancy':
                $occupancy = ($trip->getTotalSeats() - $trip->getAvailableSeats()) / $trip->getTotalSeats();
                return $occupancy >= $value;

            case 'max_occupancy':
                $occupancy = ($trip->getTotalSeats() - $trip->getAvailableSeats()) / $trip->getTotalSeats();
                return $occupancy <= $value;

            case 'day_of_week':
                $dayOfWeek = $trip->getDepartureTime()->format('N');
                return in_array($dayOfWeek, $value);

            case 'time_of_day':
                $hour = (int) $trip->getDepartureTime()->format('H');
                return $hour >= $value['start'] && $hour <= $value['end'];

            default:
                return true;
        }
    }

    /**
     * Apply min/max constraints
     */
    private function applyConstraints(float $price, array $rules): float
    {
        $minPrice = null;
        $maxPrice = null;

        foreach ($rules as $rule) {
            if ($rule->getMinPrice() !== null) {
                $ruleMin = (float) $rule->getMinPrice();
                $minPrice = $minPrice === null ? $ruleMin : max($minPrice, $ruleMin);
            }

            if ($rule->getMaxPrice() !== null) {
                $ruleMax = (float) $rule->getMaxPrice();
                $maxPrice = $maxPrice === null ? $ruleMax : min($maxPrice, $ruleMax);
            }
        }

        if ($minPrice !== null && $price < $minPrice) {
            return $minPrice;
        }

        if ($maxPrice !== null && $price > $maxPrice) {
            return $maxPrice;
        }

        return $price;
    }

    /**
     * Create pricing rule
     */
    public function createPricingRule(array $data): PricingRule
    {
        $rule = new PricingRule();
        $rule->setName($data['name']);
        $rule->setType($data['type']);
        $rule->setConditions($data['conditions'] ?? []);
        $rule->setMultiplier($data['multiplier']);
        $rule->setMinPrice($data['min_price'] ?? null);
        $rule->setMaxPrice($data['max_price'] ?? null);
        $rule->setPriority($data['priority'] ?? 0);
        $rule->setStartsAt(isset($data['starts_at']) ? new \DateTimeImmutable($data['starts_at']) : null);
        $rule->setEndsAt(isset($data['ends_at']) ? new \DateTimeImmutable($data['ends_at']) : null);

        if (isset($data['agency_id'])) {
            $agency = $this->entityManager->getRepository(Agency::class)->find($data['agency_id']);
            $rule->setAgency($agency);
        }

        $this->pricingRuleRepository->save($rule, true);

        return $rule;
    }

    /**
     * Update pricing rule
     */
    public function updatePricingRule(PricingRule $rule, array $data): PricingRule
    {
        if (isset($data['name'])) {
            $rule->setName($data['name']);
        }

        if (isset($data['type'])) {
            $rule->setType($data['type']);
        }

        if (isset($data['conditions'])) {
            $rule->setConditions($data['conditions']);
        }

        if (isset($data['multiplier'])) {
            $rule->setMultiplier($data['multiplier']);
        }

        if (isset($data['min_price'])) {
            $rule->setMinPrice($data['min_price']);
        }

        if (isset($data['max_price'])) {
            $rule->setMaxPrice($data['max_price']);
        }

        if (isset($data['priority'])) {
            $rule->setPriority($data['priority']);
        }

        if (isset($data['is_active'])) {
            $rule->setIsActive($data['is_active']);
        }

        if (isset($data['starts_at'])) {
            $rule->setStartsAt(new \DateTimeImmutable($data['starts_at']));
        }

        if (isset($data['ends_at'])) {
            $rule->setEndsAt(new \DateTimeImmutable($data['ends_at']));
        }

        $this->pricingRuleRepository->save($rule, true);

        return $rule;
    }

    /**
     * Get pricing statistics for an agency
     */
    public function getPricingStatistics(Agency $agency): array
    {
        $rules = $this->pricingRuleRepository->findActiveByAgency($agency);

        $stats = [
            'total_rules' => count($rules),
            'rules_by_type' => [],
            'average_multiplier' => 0,
        ];

        $totalMultiplier = 0;
        foreach ($rules as $rule) {
            $type = $rule->getType();
            $stats['rules_by_type'][$type] = ($stats['rules_by_type'][$type] ?? 0) + 1;
            $totalMultiplier += (float) $rule->getMultiplier();
        }

        if (count($rules) > 0) {
            $stats['average_multiplier'] = round($totalMultiplier / count($rules), 2);
        }

        return $stats;
    }
}
