<?php

namespace App\Service;

use App\Entity\MultiModalTrip;
use App\Entity\MultiModalTripSegment;
use App\Entity\TransportMode;
use App\Entity\Trip;
use App\Repository\MultiModalTripRepository;
use App\Repository\MultiModalTripSegmentRepository;
use App\Repository\TransportModeRepository;
use App\Repository\TripRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class MultiModalTripService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MultiModalTripRepository $multiModalTripRepository,
        private MultiModalTripSegmentRepository $segmentRepository,
        private TransportModeRepository $transportModeRepository,
        private TripRepository $tripRepository,
        private HttpClientInterface $httpClient,
        private string $googleMapsApiKey = ''
    ) {
    }

    /**
     * Plan a multi-modal trip
     */
    public function planTrip(
        int $userId,
        string $departureCity,
        string $arrivalCity,
        \DateTimeImmutable $departureTime,
        array $preferences = []
    ): MultiModalTrip {
        $multiModalTrip = new MultiModalTrip();
        $multiModalTrip->setUserId($userId);
        $multiModalTrip->setDepartureCity($departureCity);
        $multiModalTrip->setArrivalCity($arrivalCity);
        $multiModalTrip->setDepartureTime($departureTime);
        $multiModalTrip->setPreferences($preferences);
        $multiModalTrip->setStatus('planned');

        $this->multiModalTripRepository->save($multiModalTrip, true);

        // Plan segments based on available trips
        $this->planSegments($multiModalTrip, $preferences);

        return $multiModalTrip;
    }

    /**
     * Plan segments for a multi-modal trip
     */
    private function planSegments(MultiModalTrip $multiModalTrip, array $preferences = []): void
    {
        $departureCity = $multiModalTrip->getDepartureCity();
        $arrivalCity = $multiModalTrip->getArrivalCity();
        $departureTime = $multiModalTrip->getDepartureTime();

        // Find direct trips first
        $directTrips = $this->tripRepository->findTripsByRoute(
            $departureCity,
            $arrivalCity,
            $departureTime
        );

        if (!empty($directTrips)) {
            // Create a single segment for direct trip
            $this->createSegment($multiModalTrip, $directTrips[0], 1);
        } else {
            // Find connecting trips
            $this->planConnectingTrips($multiModalTrip, $preferences);
        }

        // Calculate totals
        $this->calculateTotals($multiModalTrip);
    }

    /**
     * Plan connecting trips for multi-modal journey
     */
    private function planConnectingTrips(MultiModalTrip $multiModalTrip, array $preferences = []): void
    {
        $departureCity = $multiModalTrip->getDepartureCity();
        $arrivalCity = $multiModalTrip->getArrivalCity();
        $departureTime = $multiModalTrip->getDepartureTime();

        // Find intermediate cities with available trips
        $intermediateTrips = $this->tripRepository->findTripsFromCity($departureCity, $departureTime);

        $segmentOrder = 1;
        $currentCity = $departureCity;
        $currentTime = $departureTime;

        foreach ($intermediateTrips as $firstLeg) {
            if ($firstLeg->getArrivalCity() === $arrivalCity) {
                // Direct trip found
                $this->createSegment($multiModalTrip, $firstLeg, $segmentOrder);
                break;
            }

            // Find connecting trip from intermediate city to destination
            $connectingTrips = $this->tripRepository->findTripsByRoute(
                $firstLeg->getArrivalCity(),
                $arrivalCity,
                $firstLeg->getArrivalTime()
            );

            if (!empty($connectingTrips)) {
                // Create first segment
                $this->createSegment($multiModalTrip, $firstLeg, $segmentOrder);
                $segmentOrder++;

                // Create second segment
                $this->createSegment($multiModalTrip, $connectingTrips[0], $segmentOrder);
                break;
            }
        }
    }

    /**
     * Create a segment for a multi-modal trip
     */
    private function createSegment(
        MultiModalTrip $multiModalTrip,
        Trip $trip,
        int $segmentOrder
    ): MultiModalTripSegment {
        $segment = new MultiModalTripSegment();
        $segment->setMultiModalTrip($multiModalTrip);
        $segment->setTrip($trip);
        $segment->setTransportMode($trip->getTransportMode());
        $segment->setSegmentOrder($segmentOrder);
        $segment->setDepartureCity($trip->getDepartureCity());
        $segment->setArrivalCity($trip->getArrivalCity());
        $segment->setDepartureTime($trip->getDepartureTime());
        $segment->setArrivalTime($trip->getArrivalTime());
        $segment->setPrice($trip->getPrice());
        $segment->setDuration($trip->getDuration());
        $segment->setDistance($trip->getDistance());
        $segment->setStatus('pending');

        $this->segmentRepository->save($segment, true);

        return $segment;
    }

    /**
     * Calculate totals for a multi-modal trip
     */
    private function calculateTotals(MultiModalTrip $multiModalTrip): void
    {
        $segments = $this->segmentRepository->findByMultiModalTrip($multiModalTrip->getId());

        $totalPrice = 0;
        $totalDuration = 0;
        $totalDistance = 0;

        foreach ($segments as $segment) {
            if ($segment->getPrice()) {
                $totalPrice += (float) $segment->getPrice();
            }
            if ($segment->getDuration()) {
                $totalDuration += $segment->getDuration();
            }
            if ($segment->getDistance()) {
                $totalDistance += $segment->getDistance();
            }
        }

        $multiModalTrip->setTotalPrice((string) $totalPrice);
        $multiModalTrip->setTotalDuration($totalDuration);
        $multiModalTrip->setTotalDistance($totalDistance);

        $this->multiModalTripRepository->save($multiModalTrip, true);
    }

    /**
     * Get multi-modal trip with segments
     */
    public function getTripWithSegments(int $tripId): ?MultiModalTrip
    {
        $trip = $this->multiModalTripRepository->find($tripId);
        if (!$trip) {
            return null;
        }

        // Load segments
        $segments = $this->segmentRepository->findByMultiModalTrip($tripId);
        foreach ($segments as $segment) {
            $trip->addSegment($segment);
        }

        return $trip;
    }

    /**
     * Update segment status
     */
    public function updateSegmentStatus(int $segmentId, string $status): MultiModalTripSegment
    {
        $segment = $this->segmentRepository->find($segmentId);
        if (!$segment) {
            throw new \InvalidArgumentException('Segment not found');
        }

        $segment->setStatus($status);
        $this->segmentRepository->save($segment, true);

        // Update parent trip status if needed
        $this->updateTripStatus($segment->getMultiModalTrip());

        return $segment;
    }

    /**
     * Update trip status based on segments
     */
    private function updateTripStatus(MultiModalTrip $multiModalTrip): void
    {
        $segments = $this->segmentRepository->findByMultiModalTrip($multiModalTrip->getId());

        $allCompleted = true;
        $anyInProgress = false;
        $anyCancelled = false;

        foreach ($segments as $segment) {
            if ($segment->getStatus() !== 'completed') {
                $allCompleted = false;
            }
            if ($segment->getStatus() === 'in_progress') {
                $anyInProgress = true;
            }
            if ($segment->getStatus() === 'cancelled') {
                $anyCancelled = true;
            }
        }

        if ($allCompleted) {
            $multiModalTrip->setStatus('completed');
        } elseif ($anyCancelled) {
            $multiModalTrip->setStatus('cancelled');
        } elseif ($anyInProgress) {
            $multiModalTrip->setStatus('in_progress');
        }

        $this->multiModalTripRepository->save($multiModalTrip, true);
    }

    /**
     * Get available transport modes
     */
    public function getAvailableTransportModes(): array
    {
        return $this->transportModeRepository->findAll();
    }

    /**
     * Search for multi-modal trip options
     */
    public function searchTripOptions(
        string $departureCity,
        string $arrivalCity,
        \DateTimeImmutable $departureTime,
        array $preferences = []
    ): array {
        $options = [];

        // Find direct trips
        $directTrips = $this->tripRepository->findTripsByRoute(
            $departureCity,
            $arrivalCity,
            $departureTime
        );

        foreach ($directTrips as $trip) {
            $options[] = [
                'type' => 'direct',
                'trip' => $trip,
                'segments' => [$trip],
                'total_price' => $trip->getPrice(),
                'total_duration' => $trip->getDuration(),
                'total_distance' => $trip->getDistance(),
            ];
        }

        // Find connecting trips
        $connectingOptions = $this->findConnectingOptions(
            $departureCity,
            $arrivalCity,
            $departureTime,
            $preferences
        );

        $options = array_merge($options, $connectingOptions);

        // Sort by total duration
        usort($options, function ($a, $b) {
            return ($a['total_duration'] ?? 0) <=> ($b['total_duration'] ?? 0);
        });

        return $options;
    }

    /**
     * Find connecting trip options
     */
    private function findConnectingOptions(
        string $departureCity,
        string $arrivalCity,
        \DateTimeImmutable $departureTime,
        array $preferences = []
    ): array {
        $options = [];

        // Find trips from departure city
        $firstLegTrips = $this->tripRepository->findTripsFromCity($departureCity, $departureTime);

        foreach ($firstLegTrips as $firstLeg) {
            if ($firstLeg->getArrivalCity() === $arrivalCity) {
                continue; // Skip direct trips
            }

            // Find connecting trips from intermediate city
            $connectingTrips = $this->tripRepository->findTripsByRoute(
                $firstLeg->getArrivalCity(),
                $arrivalCity,
                $firstLeg->getArrivalTime()
            );

            foreach ($connectingTrips as $secondLeg) {
                $totalPrice = (float) $firstLeg->getPrice() + (float) $secondLeg->getPrice();
                $totalDuration = ($firstLeg->getDuration() ?? 0) + ($secondLeg->getDuration() ?? 0);
                $totalDistance = ($firstLeg->getDistance() ?? 0) + ($secondLeg->getDistance() ?? 0);

                $options[] = [
                    'type' => 'connecting',
                    'segments' => [$firstLeg, $secondLeg],
                    'total_price' => (string) $totalPrice,
                    'total_duration' => $totalDuration,
                    'total_distance' => $totalDistance,
                    'transfer_city' => $firstLeg->getArrivalCity(),
                ];
            }
        }

        return $options;
    }

    /**
     * Book a multi-modal trip
     */
    public function bookTrip(int $tripId): MultiModalTrip
    {
        $trip = $this->multiModalTripRepository->find($tripId);
        if (!$trip) {
            throw new \InvalidArgumentException('Trip not found');
        }

        $trip->setStatus('booked');
        $this->multiModalTripRepository->save($trip, true);

        // Update segment statuses
        $segments = $this->segmentRepository->findByMultiModalTrip($tripId);
        foreach ($segments as $segment) {
            $segment->setStatus('confirmed');
            $this->segmentRepository->save($segment, true);
        }

        return $trip;
    }

    /**
     * Cancel a multi-modal trip
     */
    public function cancelTrip(int $tripId): MultiModalTrip
    {
        $trip = $this->multiModalTripRepository->find($tripId);
        if (!$trip) {
            throw new \InvalidArgumentException('Trip not found');
        }

        $trip->setStatus('cancelled');
        $this->multiModalTripRepository->save($trip, true);

        // Update segment statuses
        $segments = $this->segmentRepository->findByMultiModalTrip($tripId);
        foreach ($segments as $segment) {
            if ($segment->getStatus() !== 'completed') {
                $segment->setStatus('cancelled');
                $this->segmentRepository->save($segment, true);
            }
        }

        return $trip;
    }
}
