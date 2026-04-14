<?php

namespace App\Service;

use App\Entity\Agency;
use App\Entity\Booking;
use App\Entity\RecommendationHistory;
use App\Entity\Trip;
use App\Entity\User;
use App\Entity\UserPreference;
use App\Repository\AgencyRepository;
use App\Repository\BookingRepository;
use App\Repository\RecommendationHistoryRepository;
use App\Repository\TripRepository;
use App\Repository\UserPreferenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class RecommendationService
{
    private string $apiKey;
    private string $model;
    private string $baseUrl;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPreferenceRepository $preferenceRepository,
        private RecommendationHistoryRepository $historyRepository,
        private TripRepository $tripRepository,
        private AgencyRepository $agencyRepository,
        private BookingRepository $bookingRepository,
        private HttpClientInterface $httpClient,
        string $openaiApiKey = '',
        string $openaiModel = 'gpt-4',
        string $openaiBaseUrl = 'https://api.openai.com/v1'
    ) {
        $this->apiKey = $openaiApiKey;
        $this->model = $openaiModel;
        $this->baseUrl = $openaiBaseUrl;
    }

    /**
     * Get personalized recommendations for user
     */
    public function getRecommendations(User $user, string $type = 'trip', int $limit = 10): array
    {
        $preferences = $this->preferenceRepository->findByUser($user);
        $bookingHistory = $this->bookingRepository->findByUser($user->getId());

        // Analyze user behavior
        $userProfile = $this->analyzeUserProfile($user, $preferences, $bookingHistory);

        // Get recommendations based on type
        $recommendations = match ($type) {
            'trip' => $this->getTripRecommendations($user, $userProfile, $limit),
            'agency' => $this->getAgencyRecommendations($user, $userProfile, $limit),
            'route' => $this->getRouteRecommendations($user, $userProfile, $limit),
            'offer' => $this->getOfferRecommendations($user, $userProfile, $limit),
            default => $this->getTripRecommendations($user, $userProfile, $limit),
        };

        // Save recommendations to history
        foreach ($recommendations as $recommendation) {
            $this->saveRecommendationHistory($user, $recommendation, $type);
        }

        return $recommendations;
    }

    /**
     * Analyze user profile
     */
    private function analyzeUserProfile(User $user, ?UserPreference $preferences, array $bookingHistory): array
    {
        $profile = [
            'preferred_routes' => [],
            'preferred_agencies' => [],
            'preferred_time_slots' => [],
            'budget_range' => 'medium',
            'travel_frequency' => 'occasional',
            'last_booking_date' => null,
            'total_bookings' => count($bookingHistory),
            'average_booking_value' => 0,
        ];

        // Analyze booking history
        if (!empty($bookingHistory)) {
            $totalValue = 0;
            $routes = [];
            $agencies = [];
            $timeSlots = [];

            foreach ($bookingHistory as $booking) {
                $trip = $booking->getTrip();
                $totalValue += (float) $booking->getTotalPrice();

                // Track routes
                $routeKey = $trip->getDepartureCity() . ' → ' . $trip->getArrivalCity();
                $routes[$routeKey] = ($routes[$routeKey] ?? 0) + 1;

                // Track agencies
                $agencyId = $trip->getAgency()->getId();
                $agencies[$agencyId] = ($agencies[$agencyId] ?? 0) + 1;

                // Track time slots
                $hour = (int) $trip->getDepartureTime()->format('H');
                $timeSlot = $this->getTimeSlot($hour);
                $timeSlots[$timeSlot] = ($timeSlots[$timeSlot] ?? 0) + 1;
            }

            $profile['preferred_routes'] = array_keys(array_slice($routes, 0, 5, true));
            $profile['preferred_agencies'] = array_keys(array_slice($agencies, 0, 5, true));
            $profile['preferred_time_slots'] = array_keys(array_slice($timeSlots, 0, 3, true));
            $profile['average_booking_value'] = $totalValue / count($bookingHistory);
            $profile['last_booking_date'] = $bookingHistory[0]->getCreatedAt();

            // Determine travel frequency
            $daysSinceLastBooking = (new \DateTimeImmutable())->diff($profile['last_booking_date'])->days;
            if ($daysSinceLastBooking < 30) {
                $profile['travel_frequency'] = 'frequent';
            } elseif ($daysSinceLastBooking < 90) {
                $profile['travel_frequency'] = 'regular';
            }
        }

        // Merge with explicit preferences
        if ($preferences) {
            if ($preferences->getPreferredRoutes()) {
                $profile['preferred_routes'] = array_merge($profile['preferred_routes'], $preferences->getPreferredRoutes());
            }
            if ($preferences->getPreferredAgencies()) {
                $profile['preferred_agencies'] = array_merge($profile['preferred_agencies'], $preferences->getPreferredAgencies());
            }
            if ($preferences->getBudgetRange()) {
                $profile['budget_range'] = $preferences->getBudgetRange();
            }
        }

        return $profile;
    }

    /**
     * Get trip recommendations
     */
    private function getTripRecommendations(User $user, array $userProfile, int $limit): array
    {
        $recommendations = [];

        // Get trips based on preferred routes
        foreach ($userProfile['preferred_routes'] as $route) {
            $parts = explode(' → ', $route);
            if (count($parts) === 2) {
                $trips = $this->tripRepository->searchTrips($parts[0], $parts[1], new \DateTimeImmutable());
                foreach (array_slice($trips, 0, 3) as $trip) {
                    $recommendations[] = $this->createTripRecommendation($trip, $userProfile);
                }
            }
        }

        // Get trips from preferred agencies
        foreach ($userProfile['preferred_agencies'] as $agencyId) {
            $trips = $this->tripRepository->findByAgency($agencyId);
            foreach (array_slice($trips, 0, 3) as $trip) {
                $recommendations[] = $this->createTripRecommendation($trip, $userProfile);
            }
        }

        // Get popular trips
        $popularTrips = $this->tripRepository->findPopularTrips($limit);
        foreach ($popularTrips as $trip) {
            $recommendations[] = $this->createTripRecommendation($trip, $userProfile);
        }

        // Remove duplicates and sort by relevance
        $recommendations = $this->deduplicateAndSort($recommendations, $limit);

        return $recommendations;
    }

    /**
     * Get agency recommendations
     */
    private function getAgencyRecommendations(User $user, array $userProfile, int $limit): array
    {
        $recommendations = [];

        // Get agencies from preferred agencies
        foreach ($userProfile['preferred_agencies'] as $agencyId) {
            $agency = $this->agencyRepository->find($agencyId);
            if ($agency) {
                $recommendations[] = $this->createAgencyRecommendation($agency, $userProfile);
            }
        }

        // Get top-rated agencies
        $topAgencies = $this->agencyRepository->findTopRated($limit);
        foreach ($topAgencies as $agency) {
            $recommendations[] = $this->createAgencyRecommendation($agency, $userProfile);
        }

        // Remove duplicates and sort by relevance
        $recommendations = $this->deduplicateAndSort($recommendations, $limit);

        return $recommendations;
    }

    /**
     * Get route recommendations
     */
    private function getRouteRecommendations(User $user, array $userProfile, int $limit): array
    {
        $recommendations = [];

        // Get popular routes
        $popularRoutes = $this->tripRepository->findPopularRoutes($limit);

        foreach ($popularRoutes as $route) {
            $recommendations[] = [
                'type' => 'route',
                'data' => [
                    'departure_city' => $route['departureCity'],
                    'arrival_city' => $route['arrivalCity'],
                    'trip_count' => $route['tripCount'],
                ],
                'relevance_score' => $this->calculateRouteRelevance($route, $userProfile),
                'reasoning' => 'Popular route with high demand',
            ];
        }

        // Sort by relevance
        usort($recommendations, fn($a, $b) => $b['relevance_score'] <=> $a['relevance_score']);

        return array_slice($recommendations, 0, $limit);
    }

    /**
     * Get offer recommendations
     */
    private function getOfferRecommendations(User $user, array $userProfile, int $limit): array
    {
        $recommendations = [];

        // Get trips with discounts (low occupancy)
        $trips = $this->tripRepository->findTripsWithLowOccupancy($limit);

        foreach ($trips as $trip) {
            $occupancyRate = ($trip->getTotalSeats() - $trip->getAvailableSeats()) / $trip->getTotalSeats();
            $discountPercentage = (1 - $occupancyRate) * 20; // Up to 20% discount

            $recommendations[] = [
                'type' => 'offer',
                'data' => [
                    'trip_id' => $trip->getId(),
                    'route' => $trip->getDepartureCity() . ' → ' . $trip->getArrivalCity(),
                    'original_price' => $trip->getPrice(),
                    'discounted_price' => round((float) $trip->getPrice() * (1 - $discountPercentage / 100), 2),
                    'discount_percentage' => round($discountPercentage, 2),
                    'departure_time' => $trip->getDepartureTime()->format('Y-m-d H:i'),
                ],
                'relevance_score' => $this->calculateOfferRelevance($trip, $userProfile),
                'reasoning' => sprintf('%.0f%% discount due to low occupancy', $discountPercentage),
            ];
        }

        // Sort by relevance
        usort($recommendations, fn($a, $b) => $b['relevance_score'] <=> $a['relevance_score']);

        return array_slice($recommendations, 0, $limit);
    }

    /**
     * Create trip recommendation
     */
    private function createTripRecommendation(Trip $trip, array $userProfile): array
    {
        return [
            'type' => 'trip',
            'data' => [
                'id' => $trip->getId(),
                'route' => $trip->getDepartureCity() . ' → ' . $trip->getArrivalCity(),
                'agency' => $trip->getAgency()->getName(),
                'price' => $trip->getPrice(),
                'departure_time' => $trip->getDepartureTime()->format('Y-m-d H:i'),
                'available_seats' => $trip->getAvailableSeats(),
                'vehicle_type' => $trip->getVehicle()->getType(),
            ],
            'relevance_score' => $this->calculateTripRelevance($trip, $userProfile),
            'reasoning' => $this->generateTripReasoning($trip, $userProfile),
        ];
    }

    /**
     * Create agency recommendation
     */
    private function createAgencyRecommendation(Agency $agency, array $userProfile): array
    {
        return [
            'type' => 'agency',
            'data' => [
                'id' => $agency->getId(),
                'name' => $agency->getName(),
                'rating' => $agency->getRating(),
                'total_reviews' => $agency->getTotalReviews(),
                'city' => $agency->getCity(),
                'is_verified' => $agency->isVerified(),
            ],
            'relevance_score' => $this->calculateAgencyRelevance($agency, $userProfile),
            'reasoning' => $this->generateAgencyReasoning($agency, $userProfile),
        ];
    }

    /**
     * Calculate trip relevance score
     */
    private function calculateTripRelevance(Trip $trip, array $userProfile): float
    {
        $score = 50; // Base score

        // Route preference
        $routeKey = $trip->getDepartureCity() . ' → ' . $trip->getArrivalCity();
        if (in_array($routeKey, $userProfile['preferred_routes'])) {
            $score += 30;
        }

        // Agency preference
        if (in_array($trip->getAgency()->getId(), $userProfile['preferred_agencies'])) {
            $score += 20;
        }

        // Time slot preference
        $hour = (int) $trip->getDepartureTime()->format('H');
        $timeSlot = $this->getTimeSlot($hour);
        if (in_array($timeSlot, $userProfile['preferred_time_slots'])) {
            $score += 15;
        }

        // Price match
        $price = (float) $trip->getPrice();
        if ($userProfile['budget_range'] === 'low' && $price < 5000) {
            $score += 10;
        } elseif ($userProfile['budget_range'] === 'medium' && $price >= 5000 && $price < 15000) {
            $score += 10;
        } elseif ($userProfile['budget_range'] === 'high' && $price >= 15000) {
            $score += 10;
        }

        // Availability bonus
        if ($trip->getAvailableSeats() > 0) {
            $score += 5;
        }

        return min(100, $score);
    }

    /**
     * Calculate agency relevance score
     */
    private function calculateAgencyRelevance(Agency $agency, array $userProfile): float
    {
        $score = 50; // Base score

        // Agency preference
        if (in_array($agency->getId(), $userProfile['preferred_agencies'])) {
            $score += 30;
        }

        // Rating bonus
        $score += $agency->getRating() * 5;

        // Verified bonus
        if ($agency->isVerified()) {
            $score += 10;
        }

        // Review count bonus
        $score += min(10, $agency->getTotalReviews() / 10);

        return min(100, $score);
    }

    /**
     * Calculate route relevance score
     */
    private function calculateRouteRelevance(array $route, array $userProfile): float
    {
        $score = 50; // Base score

        // Route preference
        $routeKey = $route['departureCity'] . ' → ' . $route['arrivalCity'];
        if (in_array($routeKey, $userProfile['preferred_routes'])) {
            $score += 30;
        }

        // Popularity bonus
        $score += min(20, $route['tripCount'] / 5);

        return min(100, $score);
    }

    /**
     * Calculate offer relevance score
     */
    private function calculateOfferRelevance(Trip $trip, array $userProfile): float
    {
        $score = 50; // Base score

        // Route preference
        $routeKey = $trip->getDepartureCity() . ' → ' . $trip->getArrivalCity();
        if (in_array($routeKey, $userProfile['preferred_routes'])) {
            $score += 20;
        }

        // Agency preference
        if (in_array($trip->getAgency()->getId(), $userProfile['preferred_agencies'])) {
            $score += 15;
        }

        // Discount bonus
        $occupancyRate = ($trip->getTotalSeats() - $trip->getAvailableSeats()) / $trip->getTotalSeats();
        $discountPercentage = (1 - $occupancyRate) * 20;
        $score += $discountPercentage;

        return min(100, $score);
    }

    /**
     * Generate trip reasoning
     */
    private function generateTripReasoning(Trip $trip, array $userProfile): string
    {
        $reasons = [];

        $routeKey = $trip->getDepartureCity() . ' → ' . $trip->getArrivalCity();
        if (in_array($routeKey, $userProfile['preferred_routes'])) {
            $reasons[] = 'matches your preferred route';
        }

        if (in_array($trip->getAgency()->getId(), $userProfile['preferred_agencies'])) {
            $reasons[] = 'from your preferred agency';
        }

        $hour = (int) $trip->getDepartureTime()->format('H');
        $timeSlot = $this->getTimeSlot($hour);
        if (in_array($timeSlot, $userProfile['preferred_time_slots'])) {
            $reasons[] = 'at your preferred time';
        }

        if (empty($reasons)) {
            $reasons[] = 'popular choice among travelers';
        }

        return 'Recommended because it ' . implode(', ', $reasons);
    }

    /**
     * Generate agency reasoning
     */
    private function generateAgencyReasoning(Agency $agency, array $userProfile): string
    {
        $reasons = [];

        if (in_array($agency->getId(), $userProfile['preferred_agencies'])) {
            $reasons[] = 'one of your preferred agencies';
        }

        if ($agency->getRating() >= 4.5) {
            $reasons[] = 'highly rated by travelers';
        }

        if ($agency->isVerified()) {
            $reasons[] = 'verified and trusted';
        }

        if (empty($reasons)) {
            $reasons[] = 'top-rated agency in the area';
        }

        return 'Recommended because it is ' . implode(', ', $reasons);
    }

    /**
     * Get time slot from hour
     */
    private function getTimeSlot(int $hour): string
    {
        if ($hour >= 5 && $hour < 12) {
            return 'morning';
        } elseif ($hour >= 12 && $hour < 17) {
            return 'afternoon';
        } elseif ($hour >= 17 && $hour < 21) {
            return 'evening';
        } else {
            return 'night';
        }
    }

    /**
     * Deduplicate and sort recommendations
     */
    private function deduplicateAndSort(array $recommendations, int $limit): array
    {
        $seen = [];
        $unique = [];

        foreach ($recommendations as $recommendation) {
            $key = $recommendation['type'] . '_' . ($recommendation['data']['id'] ?? json_encode($recommendation['data']));
            if (!in_array($key, $seen)) {
                $seen[] = $key;
                $unique[] = $recommendation;
            }
        }

        // Sort by relevance score
        usort($unique, fn($a, $b) => $b['relevance_score'] <=> $a['relevance_score']);

        return array_slice($unique, 0, $limit);
    }

    /**
     * Save recommendation to history
     */
    private function saveRecommendationHistory(User $user, array $recommendation, string $type): void
    {
        $history = new RecommendationHistory();
        $history->setUser($user);
        $history->setType($type);
        $history->setRecommendationData($recommendation['data']);
        $history->setRelevanceScore((string) $recommendation['relevance_score']);

        if (isset($recommendation['data']['id']) && $type === 'trip') {
            $trip = $this->tripRepository->find($recommendation['data']['id']);
            $history->setTrip($trip);
        }

        $this->historyRepository->save($history, true);
    }

    /**
     * Mark recommendation as clicked
     */
    public function markAsClicked(int $historyId): void
    {
        $history = $this->historyRepository->find($historyId);
        if ($history) {
            $history->markAsClicked();
            $this->historyRepository->save($history, true);
        }
    }

    /**
     * Mark recommendation as booked
     */
    public function markAsBooked(int $historyId): void
    {
        $history = $this->historyRepository->find($historyId);
        if ($history) {
            $history->markAsBooked();
            $this->historyRepository->save($history, true);
        }
    }

    /**
     * Get recommendation statistics for user
     */
    public function getRecommendationStatistics(User $user): array
    {
        return [
            'total_recommendations' => $this->historyRepository->countByUser($user),
            'clicked_recommendations' => $this->historyRepository->countClickedByUser($user),
            'booked_recommendations' => $this->historyRepository->countBookedByUser($user),
            'click_through_rate' => round($this->historyRepository->getClickThroughRateByUser($user), 2),
            'conversion_rate' => round($this->historyRepository->getConversionRateByUser($user), 2),
            'average_relevance_score' => round($this->historyRepository->getAverageRelevanceScoreByUser($user), 2),
        ];
    }

    /**
     * Update user preferences
     */
    public function updateUserPreferences(User $user, array $data): UserPreference
    {
        $preferences = $this->preferenceRepository->findByUser($user);
        if (!$preferences) {
            $preferences = new UserPreference();
            $preferences->setUser($user);
        }

        if (isset($data['preferred_routes'])) {
            $preferences->setPreferredRoutes($data['preferred_routes']);
        }

        if (isset($data['preferred_agencies'])) {
            $preferences->setPreferredAgencies($data['preferred_agencies']);
        }

        if (isset($data['preferred_vehicle_types'])) {
            $preferences->setPreferredVehicleTypes($data['preferred_vehicle_types']);
        }

        if (isset($data['preferred_time_slots'])) {
            $preferences->setPreferredTimeSlots($data['preferred_time_slots']);
        }

        if (isset($data['preferred_amenities'])) {
            $preferences->setPreferredAmenities($data['preferred_amenities']);
        }

        if (isset($data['budget_range'])) {
            $preferences->setBudgetRange($data['budget_range']);
        }

        if (isset($data['prefers_eco_friendly'])) {
            $preferences->setPrefersEcoFriendly($data['prefers_eco_friendly']);
        }

        if (isset($data['prefers_express'])) {
            $preferences->setPrefersExpress($data['prefers_express']);
        }

        if (isset($data['notification_preferences'])) {
            $preferences->setNotificationPreferences($data['notification_preferences']);
        }

        $this->preferenceRepository->save($preferences, true);

        return $preferences;
    }

    /**
     * Get user preferences
     */
    public function getUserPreferences(User $user): ?UserPreference
    {
        return $this->preferenceRepository->findByUser($user);
    }
}
