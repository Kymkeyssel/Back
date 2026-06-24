<?php

namespace App\Controller\Api;

use App\Domain\TransportScope;
use App\Entity\Trip;
use App\Repository\TransportModeRepository;
use App\Repository\TripRepository;
use App\Repository\VehicleRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * API dédiée au covoiturage — séparée des lignes bus agence.
 */
#[Route('/api/carpool')]
class CarpoolController extends AbstractController
{
    public function __construct(
        private TripRepository $tripRepository,
        private TransportModeRepository $transportModeRepository,
        private VehicleRepository $vehicleRepository,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
    ) {
    }

    #[Route('/search', name: 'api_carpool_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $departureCity = $request->query->get('departureCity');
        $arrivalCity = $request->query->get('arrivalCity');
        $date = $request->query->get('date');

        if (!$departureCity || !$arrivalCity || !$date) {
            return $this->json([
                'success' => false,
                'message' => 'departureCity, arrivalCity and date are required.',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $dateObj = new \DateTimeImmutable($date);
        } catch (\Exception) {
            return $this->json(['success' => false, 'message' => 'Invalid date format.'], Response::HTTP_BAD_REQUEST);
        }

        $trips = $this->tripRepository->searchTrips(
            (string) $departureCity,
            (string) $arrivalCity,
            $dateObj,
            TransportScope::CARPOOL
        );

        return $this->json([
            'success' => true,
            'data' => array_map(fn (Trip $t) => $this->serializeCarpoolTrip($t), $trips),
            'meta' => ['total' => count($trips), 'mode' => TransportScope::CARPOOL],
        ]);
    }

    #[Route('/offers', name: 'api_carpool_offers_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createOffer(Request $request): JsonResponse
    {
        try {
            $user = $this->getUser();
            $data = json_decode($request->getContent(), true) ?? [];

            $vehicle = null;
            if (!empty($data['vehicleId'])) {
                $vehicle = $this->vehicleRepository->find($data['vehicleId']);
            } else {
                $vehicles = $this->vehicleRepository->findBy(['driver' => $user, 'type' => TransportScope::CARPOOL_VEHICLE_TYPE]);
                $vehicle = $vehicles[0] ?? null;
            }

            if (!$vehicle) {
                $agency = $this->entityManager->getRepository(\App\Entity\Agency::class)->findOneBy([]);
                if (!$agency) {
                    return $this->json([
                        'success' => false,
                        'message' => 'Aucune agence trouvée dans la base de données.',
                    ], Response::HTTP_INTERNAL_SERVER_ERROR);
                }

                $vehicle = new \App\Entity\Vehicle();
                $vehicle->setDriver($user);
                $vehicle->setAgency($agency);
                $vehicle->setType(TransportScope::CARPOOL_VEHICLE_TYPE);
                $vehicle->setBrand('Toyota');
                $vehicle->setModel('Corolla');
                $vehicle->setPlateNumber('LT-' . strtoupper(bin2hex(random_bytes(4))) . '-CP');
                $vehicle->setTotalSeats(4);
                $vehicle->setIsActive(true);

                $this->entityManager->persist($vehicle);
                try {
                    $this->entityManager->flush();
                } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
                    $vehicle->setPlateNumber('LT-' . strtoupper(bin2hex(random_bytes(4))) . '-CP');
                    $this->entityManager->flush();
                }
            }

            if ($vehicle->getDriver() !== $user) {
                return $this->json([
                    'success' => false,
                    'message' => 'Ce véhicule ne vous appartient pas.',
                ], Response::HTTP_BAD_REQUEST);
            }

            $mode = $this->transportModeRepository->findOneBy(['code' => TransportScope::CARPOOL]);
            if (!$mode) {
                return $this->json(['success' => false, 'message' => 'Mode covoiturage non configuré.'], Response::HTTP_NOT_FOUND);
            }

            $required = ['departureCity', 'arrivalCity', 'departureAddress', 'arrivalAddress', 'departureTime', 'price', 'totalSeats'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return $this->json(['success' => false, 'message' => ucfirst($field) . ' est requis.'], Response::HTTP_BAD_REQUEST);
                }
            }

            $trip = new Trip();
            $trip->setAgency($vehicle->getAgency());
            $trip->setVehicle($vehicle);
            $trip->setTransportMode($mode);
            $trip->setDepartureCity($data['departureCity']);
            $trip->setArrivalCity($data['arrivalCity']);
            $trip->setDepartureAddress($data['departureAddress']);
            $trip->setArrivalAddress($data['arrivalAddress']);
            $trip->setDepartureTime(new \DateTimeImmutable($data['departureTime']));
            $trip->setArrivalTime(isset($data['arrivalTime']) ? new \DateTimeImmutable($data['arrivalTime']) : null);
            $trip->setPrice((string) $data['price']);
            $trip->setTotalSeats((int) $data['totalSeats']);
            $trip->setAvailableSeats((int) $data['totalSeats']);
            $trip->setStatus('scheduled');

            $errors = $this->validator->validate($trip);
            if (count($errors) > 0) {
                $messages = [];
                foreach ($errors as $e) {
                    $messages[] = $e->getMessage();
                }
                return $this->json(['success' => false, 'message' => 'Validation échouée.', 'errors' => $messages], Response::HTTP_BAD_REQUEST);
            }

            $this->entityManager->persist($trip);

            try {
                $this->entityManager->flush();
            } catch (UniqueConstraintViolationException $e) {
                $this->syncTripSequence();

                $this->entityManager->clear();
                $agency = $this->entityManager->getRepository(\App\Entity\Agency::class)->find($vehicle->getAgency()->getId());
                $vehicle = $this->entityManager->getRepository(\App\Entity\Vehicle::class)->find($vehicle->getId());
                $mode = $this->entityManager->getRepository(\App\Entity\TransportMode::class)->find($mode->getId());
                $freshTrip = new Trip();
                $freshTrip->setAgency($agency);
                $freshTrip->setVehicle($vehicle);
                $freshTrip->setTransportMode($mode);
                $freshTrip->setDepartureCity($data['departureCity']);
                $freshTrip->setArrivalCity($data['arrivalCity']);
                $freshTrip->setDepartureAddress($data['departureAddress']);
                $freshTrip->setArrivalAddress($data['arrivalAddress']);
                $freshTrip->setDepartureTime(new \DateTimeImmutable($data['departureTime']));
                $freshTrip->setArrivalTime(isset($data['arrivalTime']) ? new \DateTimeImmutable($data['arrivalTime']) : null);
                $freshTrip->setPrice((string) $data['price']);
                $freshTrip->setTotalSeats((int) $data['totalSeats']);
                $freshTrip->setAvailableSeats((int) $data['totalSeats']);
                $freshTrip->setStatus('scheduled');
                $this->entityManager->persist($freshTrip);
                $this->entityManager->flush();
                $trip = $freshTrip;
            }

            return $this->json([
                'success' => true,
                'message' => 'Offre de covoiturage publiée !',
                'data' => $this->serializeCarpoolTrip($trip),
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la création de l\'offre : ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/my-offers', name: 'api_carpool_my_offers', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function myOffers(): JsonResponse
    {
        $user = $this->getUser();
        $trips = array_filter(
            $this->tripRepository->findTripsForDriver($user),
            fn (Trip $t) => $t->getTransportMode()?->getCode() === TransportScope::CARPOOL
        );

        return $this->json([
            'success' => true,
            'data' => array_map(fn (Trip $t) => $this->serializeCarpoolTrip($t), array_values($trips)),
        ]);
    }

    private function serializeCarpoolTrip(Trip $trip): array
    {
        $driver = $trip->getVehicle()?->getDriver();

        return [
            'id' => $trip->getId(),
            'departureCity' => $trip->getDepartureCity(),
            'arrivalCity' => $trip->getArrivalCity(),
            'departureTime' => $trip->getDepartureTime()->format('Y-m-d H:i:s'),
            'price' => $trip->getPrice(),
            'availableSeats' => $trip->getAvailableSeats(),
            'totalSeats' => $trip->getTotalSeats(),
            'status' => $trip->getStatus(),
            'driver' => $driver ? [
                'id' => $driver->getId(),
                'fullName' => trim($driver->getFirstName() . ' ' . $driver->getLastName()),
            ] : null,
            'vehicle' => [
                'brand' => $trip->getVehicle()?->getBrand(),
                'model' => $trip->getVehicle()?->getModel(),
            ],
        ];
    }

    private function syncTripSequence(): void
    {
        $conn = $this->entityManager->getConnection();
        $conn->executeStatement("SELECT setval('trips_id_seq', COALESCE((SELECT MAX(id) FROM trips), 0) + 1, false)");
    }
}
