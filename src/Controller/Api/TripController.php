<?php

namespace App\Controller\Api;

use App\Entity\Trip;
use App\Repository\AgencyRepository;
use App\Repository\BookingRepository;
use App\Repository\TransportModeRepository;
use App\Repository\TripRepository;
use App\Repository\VehicleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class TripController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TripRepository $tripRepository,
        private AgencyRepository $agencyRepository,
        private VehicleRepository $vehicleRepository,
        private TransportModeRepository $transportModeRepository,
        private BookingRepository $bookingRepository,
        private ValidatorInterface $validator
    ) {
    }

    #[Route('/api/trips', name: 'api_trips', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 20);
        $agencyId = $request->query->getInt('agencyId');

        if ($agencyId) {
            $trips = $this->tripRepository->findByAgency($agencyId);
        } else {
            $trips = $this->tripRepository->findScheduledTrips();
        }

        $data = [];
        foreach ($trips as $trip) {
            $data[] = $this->serializeTrip($trip);
        }

        return $this->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => count($data)
            ]
        ]);
    }

    #[Route('/api/trips/{id}', name: 'api_trip_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $trip = $this->tripRepository->find($id);

        if (!$trip) {
            return $this->json([
                'success' => false,
                'message' => 'Trip not found.'
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'success' => true,
            'data' => $this->serializeTrip($trip)
        ]);
    }

    #[Route('/api/trips/{id}/seats', name: 'api_trip_seats', methods: ['GET'])]
    public function seats(int $id): JsonResponse
    {
        $trip = $this->tripRepository->find($id);

        if (!$trip) {
            return $this->json([
                'success' => false,
                'message' => 'Trip not found.'
            ], Response::HTTP_NOT_FOUND);
        }

        $occupied = [];
        foreach ($this->bookingRepository->findByTrip($id) as $booking) {
            foreach ($booking->getSeatNumbers() as $label) {
                if (is_string($label) && $label !== '') {
                    $occupied[strtoupper($label)] = true;
                }
            }
        }

        $total = max(1, $trip->getTotalSeats());
        $cols = 4;
        $rows = (int) ceil($total / $cols);
        $rowLetters = range('A', 'Z');
        $cells = [];

        for ($r = 0; $r < $rows; ++$r) {
            for ($c = 0; $c < $cols; ++$c) {
                $idx = $r * $cols + $c + 1;
                if ($idx > $total) {
                    break 2;
                }
                $label = ($rowLetters[$r] ?? 'Z') . ($c + 1);
                $key = strtoupper($label);
                $cells[] = [
                    'id' => $label,
                    'row' => $r,
                    'col' => $c,
                    'available' => !isset($occupied[$key]),
                ];
            }
        }

        return $this->json([
            'success' => true,
            'data' => [
                'tripId' => $trip->getId(),
                'rows' => $rows,
                'colsPerRow' => $cols,
                'aisleAfterColumn' => 2,
                'cells' => $cells,
            ],
        ]);
    }

    #[Route('/api/trips/{id}/tracking', name: 'api_trip_tracking', methods: ['GET'])]
    public function tracking(int $id): JsonResponse
    {
        $trip = $this->tripRepository->find($id);

        if (!$trip) {
            return $this->json([
                'success' => false,
                'message' => 'Trip not found.'
            ], Response::HTTP_NOT_FOUND);
        }

        $lat = $trip->getCurrentLatitude();
        $lng = $trip->getCurrentLongitude();

        if ($lat === null || $lng === null) {
            $lat = $trip->getAgency()->getLatitude() ?? 4.0511;
            $lng = $trip->getAgency()->getLongitude() ?? 9.7679;
        }

        return $this->json([
            'success' => true,
            'data' => [
                'tripId' => $trip->getId(),
                'latitude' => $lat,
                'longitude' => $lng,
                'status' => $trip->getStatus(),
                'departureCity' => $trip->getDepartureCity(),
                'arrivalCity' => $trip->getArrivalCity(),
                'departureTime' => $trip->getDepartureTime()->format('Y-m-d H:i:s'),
                'updatedAt' => $trip->getUpdatedAt()->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    #[Route('/api/trips/search', name: 'api_trip_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $departureCity = $request->query->get('departureCity');
        $arrivalCity = $request->query->get('arrivalCity');
        $date = $request->query->get('date');
        $transportModeCode = $request->query->get('transportModeCode');

        if (!$departureCity || !$arrivalCity || !$date) {
            return $this->json([
                'success' => false,
                'message' => 'departureCity, arrivalCity and date are required.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $dateObj = \DateTime::createFromFormat('Y-m-d', $date);
        if (!$dateObj) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid date format. Use Y-m-d.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $trips = $this->tripRepository->searchTrips($departureCity, $arrivalCity, $dateObj, is_string($transportModeCode) ? $transportModeCode : null);

        $data = [];
        foreach ($trips as $trip) {
            $data[] = $this->serializeTrip($trip);
        }

        return $this->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'departureCity' => $departureCity,
                'arrivalCity' => $arrivalCity,
                'date' => $date,
                'transportModeCode' => is_string($transportModeCode) ? $transportModeCode : null,
                'total' => count($data)
            ]
        ]);
    }

    #[Route('/api/trips', name: 'api_trip_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'Authentication required.'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);

        // Validate required fields
        $requiredFields = ['agencyId', 'vehicleId', 'transportModeId', 'departureCity', 'arrivalCity', 'departureAddress', 'arrivalAddress', 'departureTime', 'price', 'totalSeats'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                return $this->json([
                    'success' => false,
                    'message' => ucfirst($field) . ' is required.'
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        // Get agency
        $agency = $this->agencyRepository->find($data['agencyId']);
        if (!$agency) {
            return $this->json([
                'success' => false,
                'message' => 'Agency not found.'
            ], Response::HTTP_NOT_FOUND);
        }

        // Check ownership
        if ($agency->getOwner() !== $user && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json([
                'success' => false,
                'message' => 'You do not have permission to create trips for this agency.'
            ], Response::HTTP_FORBIDDEN);
        }

        // Get transport mode (intercity bus or carpool only)
        $transportMode = $this->transportModeRepository->find($data['transportModeId']);
        if (!$transportMode) {
            return $this->json([
                'success' => false,
                'message' => 'Transport mode not found.'
            ], Response::HTTP_NOT_FOUND);
        }

        // Get vehicle
        $vehicle = $this->vehicleRepository->find($data['vehicleId']);
        if (!$vehicle) {
            return $this->json([
                'success' => false,
                'message' => 'Vehicle not found.'
            ], Response::HTTP_NOT_FOUND);
        }

        // Create trip
        $trip = new Trip();
        $trip->setAgency($agency);
        $trip->setVehicle($vehicle);
        $trip->setTransportMode($transportMode);
        $trip->setDepartureCity($data['departureCity']);
        $trip->setArrivalCity($data['arrivalCity']);
        $trip->setDepartureAddress($data['departureAddress']);
        $trip->setArrivalAddress($data['arrivalAddress']);
        $trip->setDepartureTime(new \DateTimeImmutable($data['departureTime']));
        $trip->setArrivalTime(isset($data['arrivalTime']) ? new \DateTimeImmutable($data['arrivalTime']) : null);
        $trip->setPrice($data['price']);
        $trip->setTotalSeats($data['totalSeats']);
        $trip->setAvailableSeats($data['totalSeats']);
        $trip->setDistance($data['distance'] ?? null);
        $trip->setDuration($data['duration'] ?? null);
        $trip->setBoardingPlatform($data['boardingPlatform'] ?? null);

        // Validate
        $errors = $this->validator->validate($trip);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return $this->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $errorMessages
            ], Response::HTTP_BAD_REQUEST);
        }

        // Save
        $this->entityManager->persist($trip);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Trip created successfully.',
            'data' => $this->serializeTrip($trip)
        ], Response::HTTP_CREATED);
    }

    #[Route('/api/trips/{id}', name: 'api_trip_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'Authentication required.'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $trip = $this->tripRepository->find($id);

        if (!$trip) {
            return $this->json([
                'success' => false,
                'message' => 'Trip not found.'
            ], Response::HTTP_NOT_FOUND);
        }

        // Check ownership
        if ($trip->getAgency()->getOwner() !== $user && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json([
                'success' => false,
                'message' => 'You do not have permission to update this trip.'
            ], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        // Update fields
        if (isset($data['departureCity'])) {
            $trip->setDepartureCity($data['departureCity']);
        }
        if (isset($data['arrivalCity'])) {
            $trip->setArrivalCity($data['arrivalCity']);
        }
        if (isset($data['departureAddress'])) {
            $trip->setDepartureAddress($data['departureAddress']);
        }
        if (isset($data['arrivalAddress'])) {
            $trip->setArrivalAddress($data['arrivalAddress']);
        }
        if (isset($data['departureTime'])) {
            $trip->setDepartureTime(new \DateTimeImmutable($data['departureTime']));
        }
        if (isset($data['arrivalTime'])) {
            $trip->setArrivalTime(new \DateTimeImmutable($data['arrivalTime']));
        }
        if (isset($data['price'])) {
            $trip->setPrice($data['price']);
        }
        if (isset($data['status'])) {
            $trip->setStatus($data['status']);
        }
        if (isset($data['transportModeId'])) {
            $mode = $this->transportModeRepository->find($data['transportModeId']);
            if ($mode) {
                $trip->setTransportMode($mode);
            }
        }
        if (isset($data['vehicleId'])) {
            $vehicle = $this->vehicleRepository->find($data['vehicleId']);
            if ($vehicle) {
                $trip->setVehicle($vehicle);
            }
        }
        $errors = $this->validator->validate($trip);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return $this->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $errorMessages
            ], Response::HTTP_BAD_REQUEST);
        }

        // Save
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Trip updated successfully.',
            'data' => $this->serializeTrip($trip)
        ]);
    }

    #[Route('/api/trips/{id}', name: 'api_trip_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'Authentication required.'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $trip = $this->tripRepository->find($id);

        if (!$trip) {
            return $this->json([
                'success' => false,
                'message' => 'Trip not found.'
            ], Response::HTTP_NOT_FOUND);
        }

        // Check ownership
        if ($trip->getAgency()->getOwner() !== $user && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json([
                'success' => false,
                'message' => 'You do not have permission to delete this trip.'
            ], Response::HTTP_FORBIDDEN);
        }

        // Soft delete (set cancelled)
        $trip->setStatus('cancelled');
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Trip cancelled successfully.'
        ]);
    }

    #[Route('/api/trips/best-prices', name: 'api_trip_best_prices', methods: ['GET'])]
    public function bestPrices(Request $request): JsonResponse
    {
        $mode = $request->query->get('mode', 'bus');

        try {
            $modeCode = match ($mode) {
                'carpool', 'covoiturage' => \App\Domain\TransportScope::CARPOOL,
                default => \App\Domain\TransportScope::INTERCITY_BUS,
            };
        } catch (\UnhandledMatchError) {
            $modeCode = \App\Domain\TransportScope::INTERCITY_BUS;
        }

        $trips = $this->tripRepository->findBestPricesByMode($modeCode, 9);

        $data = [];
        foreach ($trips as $trip) {
            $data[] = $this->serializeTrip($trip);
        }

        return $this->json([
            'success' => true,
            'data' => $data,
            'meta' => ['mode' => $mode, 'total' => count($data)]
        ]);
    }

    #[Route('/api/trips/carpool', name: 'api_trip_carpool', methods: ['GET'])]
    public function carpoolTrips(): JsonResponse
    {
        $trips = $this->tripRepository->findCarpoolTrips();

        $data = [];
        foreach ($trips as $trip) {
            $data[] = $this->serializeTrip($trip);
        }

        return $this->json([
            'success' => true,
            'data' => $data,
            'meta' => ['total' => count($data)]
        ]);
    }

    private function serializeTrip(Trip $trip): array
    {
        return [
            'id' => $trip->getId(),
            'agency' => [
                'id' => $trip->getAgency()->getId(),
                'name' => $trip->getAgency()->getName(),
                'slug' => $trip->getAgency()->getSlug(),
            ],
            'vehicle' => array_filter([
                'id' => $trip->getVehicle()->getId(),
                'type' => $trip->getVehicle()->getType(),
                'brand' => $trip->getVehicle()->getBrand(),
                'model' => $trip->getVehicle()->getModel(),
                'totalSeats' => $trip->getVehicle()->getTotalSeats(),
                'seatLayout' => $trip->getVehicle()->getSeatLayout(),
                'driver' => $trip->getVehicle()->getDriver() ? [
                    'id' => $trip->getVehicle()->getDriver()->getId(),
                    'fullName' => $trip->getVehicle()->getDriver()->getFullName(),
                ] : null,
            ], fn ($v) => $v !== null),
            'transportMode' => [
                'id' => $trip->getTransportMode()->getId(),
                'code' => $trip->getTransportMode()->getCode(),
                'name' => $trip->getTransportMode()->getName(),
            ],
            'departureCity' => $trip->getDepartureCity(),
            'arrivalCity' => $trip->getArrivalCity(),
            'departureAddress' => $trip->getDepartureAddress(),
            'arrivalAddress' => $trip->getArrivalAddress(),
            'departureTime' => $trip->getDepartureTime()->format('Y-m-d H:i:s'),
            'arrivalTime' => $trip->getArrivalTime()?->format('Y-m-d H:i:s'),
            'price' => $trip->getPrice(),
            'availableSeats' => $trip->getAvailableSeats(),
            'totalSeats' => $trip->getTotalSeats(),
            'boardingPlatform' => $trip->getBoardingPlatform(),
            'status' => $trip->getStatus(),
            'distance' => $trip->getDistance(),
            'duration' => $trip->getDuration(),
            'departureLatitude' => $trip->getDepartureLatitude(),
            'departureLongitude' => $trip->getDepartureLongitude(),
            'arrivalLatitude' => $trip->getArrivalLatitude(),
            'arrivalLongitude' => $trip->getArrivalLongitude(),
            'currentLatitude' => $trip->getCurrentLatitude(),
            'currentLongitude' => $trip->getCurrentLongitude(),
            'createdAt' => $trip->getCreatedAt()->format('Y-m-d H:i:s'),
            'updatedAt' => $trip->getUpdatedAt()->format('Y-m-d H:i:s'),
        ];
    }
}
