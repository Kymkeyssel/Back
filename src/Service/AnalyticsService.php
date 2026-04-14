<?php

namespace App\Service;

use App\Entity\Agency;
use App\Entity\AnalyticsDashboard;
use App\Entity\AnalyticsMetric;
use App\Entity\Booking;
use App\Entity\Payment;
use App\Entity\Trip;
use App\Entity\User;
use App\Repository\AnalyticsDashboardRepository;
use App\Repository\AnalyticsMetricRepository;
use App\Repository\BookingRepository;
use App\Repository\PaymentRepository;
use App\Repository\TripRepository;
use Doctrine\ORM\EntityManagerInterface;

class AnalyticsService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AnalyticsDashboardRepository $dashboardRepository,
        private AnalyticsMetricRepository $metricRepository,
        private BookingRepository $bookingRepository,
        private PaymentRepository $paymentRepository,
        private TripRepository $tripRepository
    ) {
    }

    /**
     * Create a new dashboard
     */
    public function createDashboard(User $user, array $data): AnalyticsDashboard
    {
        $dashboard = new AnalyticsDashboard();
        $dashboard->setName($data['name']);
        $dashboard->setType($data['type']);
        $dashboard->setUser($user);
        $dashboard->setLayout($data['layout'] ?? []);
        $dashboard->setWidgets($data['widgets'] ?? []);
        $dashboard->setFilters($data['filters'] ?? null);
        $dashboard->setIsDefault($data['is_default'] ?? false);
        $dashboard->setIsPublic($data['is_public'] ?? false);

        if (isset($data['agency_id'])) {
            $agency = $this->entityManager->getRepository(Agency::class)->find($data['agency_id']);
            $dashboard->setAgency($agency);
        }

        $this->dashboardRepository->save($dashboard, true);

        return $dashboard;
    }

    /**
     * Update dashboard
     */
    public function updateDashboard(AnalyticsDashboard $dashboard, array $data): AnalyticsDashboard
    {
        if (isset($data['name'])) {
            $dashboard->setName($data['name']);
        }

        if (isset($data['layout'])) {
            $dashboard->setLayout($data['layout']);
        }

        if (isset($data['widgets'])) {
            $dashboard->setWidgets($data['widgets']);
        }

        if (isset($data['filters'])) {
            $dashboard->setFilters($data['filters']);
        }

        if (isset($data['is_default'])) {
            $dashboard->setIsDefault($data['is_default']);
        }

        if (isset($data['is_public'])) {
            $dashboard->setIsPublic($data['is_public']);
        }

        $this->dashboardRepository->save($dashboard, true);

        return $dashboard;
    }

    /**
     * Get dashboard with metrics
     */
    public function getDashboardWithMetrics(AnalyticsDashboard $dashboard): array
    {
        $metrics = $this->metricRepository->findByDashboard($dashboard);

        return [
            'dashboard' => $this->serializeDashboard($dashboard),
            'metrics' => array_map(fn(AnalyticsMetric $metric) => $this->serializeMetric($metric), $metrics),
        ];
    }

    /**
     * Calculate revenue metrics for agency
     */
    public function calculateRevenueMetrics(Agency $agency, string $period = 'month'): array
    {
        $now = new \DateTimeImmutable();
        $startDate = $this->getStartDateForPeriod($period, $now);
        $previousStartDate = $this->getStartDateForPeriod($period, $startDate);

        // Current period revenue
        $currentRevenue = $this->paymentRepository->sumCompletedPaymentsByDateRange($startDate, $now);

        // Previous period revenue
        $previousRevenue = $this->paymentRepository->sumCompletedPaymentsByDateRange($previousStartDate, $startDate);

        // Calculate change
        $changePercentage = 0;
        if ($previousRevenue > 0) {
            $changePercentage = (($currentRevenue - $previousRevenue) / $previousRevenue) * 100;
        }

        return [
            'current' => $currentRevenue,
            'previous' => $previousRevenue,
            'change_percentage' => round($changePercentage, 2),
            'period' => $period,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $now->format('Y-m-d'),
        ];
    }

    /**
     * Calculate booking metrics for agency
     */
    public function calculateBookingMetrics(Agency $agency, string $period = 'month'): array
    {
        $now = new \DateTimeImmutable();
        $startDate = $this->getStartDateForPeriod($period, $now);
        $previousStartDate = $this->getStartDateForPeriod($period, $startDate);

        // Current period bookings
        $currentBookings = $this->bookingRepository->countBookingsByAgencyAndDateRange($agency, $startDate, $now);

        // Previous period bookings
        $previousBookings = $this->bookingRepository->countBookingsByAgencyAndDateRange($agency, $previousStartDate, $startDate);

        // Calculate change
        $changePercentage = 0;
        if ($previousBookings > 0) {
            $changePercentage = (($currentBookings - $previousBookings) / $previousBookings) * 100;
        }

        return [
            'current' => $currentBookings,
            'previous' => $previousBookings,
            'change_percentage' => round($changePercentage, 2),
            'period' => $period,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $now->format('Y-m-d'),
        ];
    }

    /**
     * Calculate trip metrics for agency
     */
    public function calculateTripMetrics(Agency $agency, string $period = 'month'): array
    {
        $now = new \DateTimeImmutable();
        $startDate = $this->getStartDateForPeriod($period, $now);
        $previousStartDate = $this->getStartDateForPeriod($period, $startDate);

        // Current period trips
        $currentTrips = $this->tripRepository->countTripsByAgencyAndDateRange($agency, $startDate, $now);

        // Previous period trips
        $previousTrips = $this->tripRepository->countTripsByAgencyAndDateRange($agency, $previousStartDate, $startDate);

        // Calculate change
        $changePercentage = 0;
        if ($previousTrips > 0) {
            $changePercentage = (($currentTrips - $previousTrips) / $previousTrips) * 100;
        }

        return [
            'current' => $currentTrips,
            'previous' => $previousTrips,
            'change_percentage' => round($changePercentage, 2),
            'period' => $period,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $now->format('Y-m-d'),
        ];
    }

    /**
     * Calculate occupancy rate for agency
     */
    public function calculateOccupancyRate(Agency $agency, string $period = 'month'): array
    {
        $now = new \DateTimeImmutable();
        $startDate = $this->getStartDateForPeriod($period, $now);

        $trips = $this->tripRepository->findByAgencyAndDateRange($agency, $startDate, $now);

        $totalSeats = 0;
        $bookedSeats = 0;

        foreach ($trips as $trip) {
            $totalSeats += $trip->getTotalSeats();
            $bookedSeats += ($trip->getTotalSeats() - $trip->getAvailableSeats());
        }

        $occupancyRate = $totalSeats > 0 ? ($bookedSeats / $totalSeats) * 100 : 0;

        return [
            'rate' => round($occupancyRate, 2),
            'total_seats' => $totalSeats,
            'booked_seats' => $bookedSeats,
            'available_seats' => $totalSeats - $bookedSeats,
            'period' => $period,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $now->format('Y-m-d'),
        ];
    }

    /**
     * Get top routes for agency
     */
    public function getTopRoutes(Agency $agency, int $limit = 10): array
    {
        $routes = $this->bookingRepository->getTopRoutesByAgency($agency, $limit);

        return array_map(fn($route) => [
            'departure_city' => $route['departureCity'],
            'arrival_city' => $route['arrivalCity'],
            'booking_count' => (int) $route['bookingCount'],
            'total_revenue' => (float) $route['totalRevenue'],
        ], $routes);
    }

    /**
     * Get revenue by date range
     */
    public function getRevenueByDateRange(Agency $agency, \DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array
    {
        $payments = $this->paymentRepository->findCompletedPaymentsByAgencyAndDateRange($agency, $startDate, $endDate);

        $revenueByDate = [];
        foreach ($payments as $payment) {
            $date = $payment->getCreatedAt()->format('Y-m-d');
            $revenueByDate[$date] = ($revenueByDate[$date] ?? 0) + (float) $payment->getAmount();
        }

        return [
            'total' => array_sum($revenueByDate),
            'by_date' => $revenueByDate,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
        ];
    }

    /**
     * Save metric to dashboard
     */
    public function saveMetric(AnalyticsDashboard $dashboard, string $name, string $type, ?string $value, array $data = [], ?string $period = null): AnalyticsMetric
    {
        $metric = new AnalyticsMetric();
        $metric->setDashboard($dashboard);
        $metric->setName($name);
        $metric->setType($type);
        $metric->setValue($value);
        $metric->setData($data);
        $metric->setPeriod($period);

        // Get previous metric for comparison
        $previousMetric = $this->metricRepository->findLatestByDashboardAndName($dashboard, $name);
        if ($previousMetric) {
            $metric->setPreviousValue($previousMetric->getValue());
            $metric->calculateChangePercentage();
        }

        $this->metricRepository->save($metric, true);

        return $metric;
    }

    /**
     * Get start date for period
     */
    private function getStartDateForPeriod(string $period, \DateTimeImmutable $referenceDate): \DateTimeImmutable
    {
        return match ($period) {
            'day' => $referenceDate->modify('-1 day'),
            'week' => $referenceDate->modify('-1 week'),
            'month' => $referenceDate->modify('-1 month'),
            'quarter' => $referenceDate->modify('-3 months'),
            'year' => $referenceDate->modify('-1 year'),
            default => $referenceDate->modify('-1 month'),
        };
    }

    /**
     * Serialize dashboard
     */
    private function serializeDashboard(AnalyticsDashboard $dashboard): array
    {
        return [
            'id' => $dashboard->getId(),
            'name' => $dashboard->getName(),
            'type' => $dashboard->getType(),
            'layout' => $dashboard->getLayout(),
            'widgets' => $dashboard->getWidgets(),
            'filters' => $dashboard->getFilters(),
            'is_default' => $dashboard->isDefault(),
            'is_public' => $dashboard->isPublic(),
            'agency' => $dashboard->getAgency() ? [
                'id' => $dashboard->getAgency()->getId(),
                'name' => $dashboard->getAgency()->getName(),
            ] : null,
            'created_at' => $dashboard->getCreatedAt()->format('c'),
            'updated_at' => $dashboard->getUpdatedAt()->format('c'),
        ];
    }

    /**
     * Serialize metric
     */
    private function serializeMetric(AnalyticsMetric $metric): array
    {
        return [
            'id' => $metric->getId(),
            'name' => $metric->getName(),
            'type' => $metric->getType(),
            'value' => $metric->getValue(),
            'data' => $metric->getData(),
            'unit' => $metric->getUnit(),
            'period' => $metric->getPeriod(),
            'start_date' => $metric->getStartDate()?->format('Y-m-d'),
            'end_date' => $metric->getEndDate()?->format('Y-m-d'),
            'previous_value' => $metric->getPreviousValue(),
            'change_percentage' => $metric->getChangePercentage(),
            'calculated_at' => $metric->getCalculatedAt()->format('c'),
            'created_at' => $metric->getCreatedAt()->format('c'),
        ];
    }
}
