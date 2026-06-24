<?php

namespace App\Controller\Api;

use App\Entity\Agency;
use App\Entity\Booking;
use App\Repository\BookingRepository;
use App\Repository\TripRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class BookingController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BookingRepository $bookingRepository,
        private TripRepository $tripRepository,
        private ValidatorInterface $validator
    ) {
    }

    #[Route('/api/bookings', name: 'api_bookings', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'Authentication required.'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $bookings = $this->isGranted('ROLE_ADMIN')
            ? $this->bookingRepository->findAllRecent()
            : $this->bookingRepository->findByUser($user->getId());

        $data = [];
        foreach ($bookings as $booking) {
            $data[] = $this->serializeBooking($booking);
        }

        return $this->json([
            'success' => true,
            'data' => $data
        ]);
    }

    #[Route('/api/bookings/{id}', name: 'api_booking_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'Authentication required.'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $booking = $this->bookingRepository->find($id);

        if (!$booking) {
            return $this->json([
                'success' => false,
                'message' => 'Booking not found.'
            ], Response::HTTP_NOT_FOUND);
        }

        // Check ownership
        if ($booking->getUser() !== $user && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json([
                'success' => false,
                'message' => 'You do not have permission to view this booking.'
            ], Response::HTTP_FORBIDDEN);
        }

        return $this->json([
            'success' => true,
            'data' => $this->serializeBooking($booking)
        ]);
    }

    #[Route('/api/bookings', name: 'api_booking_create', methods: ['POST'])]
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
        $requiredFields = ['tripId', 'numberOfSeats', 'passengers'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                return $this->json([
                    'success' => false,
                    'message' => ucfirst($field) . ' is required.'
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        // Get trip
        $trip = $this->tripRepository->find($data['tripId']);
        if (!$trip) {
            return $this->json([
                'success' => false,
                'message' => 'Trip not found.'
            ], Response::HTTP_NOT_FOUND);
        }

        // Check available seats
        if ($trip->getAvailableSeats() < $data['numberOfSeats']) {
            return $this->json([
                'success' => false,
                'message' => 'Not enough seats available.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Generate reference
        $reference = 'BK-' . strtoupper(substr(md5(uniqid()), 0, 8));

        // Calculate total price
        $totalPrice = bcmul($trip->getPrice(), (string) $data['numberOfSeats'], 2);

        // Create booking
        $booking = new Booking();
        $booking->setReference($reference);
        $booking->setUser($user);
        $booking->setTrip($trip);
        $booking->setNumberOfSeats($data['numberOfSeats']);
        $booking->setTotalPrice($totalPrice);
        $booking->setPassengers($data['passengers']);
        $booking->setSeatNumbers($data['seatNumbers'] ?? []);
        $booking->setSpecialRequests($data['specialRequests'] ?? null);

        // Validate
        $errors = $this->validator->validate($booking);
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

        // Update available seats
        $trip->setAvailableSeats($trip->getAvailableSeats() - $data['numberOfSeats']);

        // Save
        $this->entityManager->persist($booking);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Booking created successfully.',
            'data' => $this->serializeBooking($booking)
        ], Response::HTTP_CREATED);
    }

    #[Route('/api/bookings/{id}/cancel', name: 'api_booking_cancel', methods: ['PUT'])]
    public function cancel(int $id): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'Authentication required.'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $booking = $this->bookingRepository->find($id);

        if (!$booking) {
            return $this->json([
                'success' => false,
                'message' => 'Booking not found.'
            ], Response::HTTP_NOT_FOUND);
        }

        // Check ownership
        if ($booking->getUser() !== $user && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json([
                'success' => false,
                'message' => 'You do not have permission to cancel this booking.'
            ], Response::HTTP_FORBIDDEN);
        }

        // Check if can be cancelled
        if ($booking->getStatus() === 'cancelled') {
            return $this->json([
                'success' => false,
                'message' => 'Booking is already cancelled.'
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($booking->getStatus() === 'completed') {
            return $this->json([
                'success' => false,
                'message' => 'Completed bookings cannot be cancelled.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Cancel booking
        $booking->setStatus('cancelled');
        $booking->setCancelledAt(new \DateTimeImmutable());

        // Restore available seats
        $trip = $booking->getTrip();
        $trip->setAvailableSeats($trip->getAvailableSeats() + $booking->getNumberOfSeats());

        // Save
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Booking cancelled successfully.',
            'data' => $this->serializeBooking($booking)
        ]);
    }

    #[Route('/api/agencies/{id}/bookings', name: 'api_agency_bookings_list', methods: ['GET'])]
    public function agencyBookings(int $id): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['success' => false, 'message' => 'Authentication required.'], Response::HTTP_UNAUTHORIZED);
        }

        $agency = $this->entityManager->getRepository(\App\Entity\Agency::class)->find($id);
        if (!$agency) {
            return $this->json(['success' => false, 'message' => 'Agency not found.'], Response::HTTP_NOT_FOUND);
        }

        if ($agency->getOwner() !== $user && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['success' => false, 'message' => 'Access denied.'], Response::HTTP_FORBIDDEN);
        }

        $bookings = $this->bookingRepository->findByAgency($id);
        $data = [];
        foreach ($bookings as $booking) {
            $data[] = $this->serializeBooking($booking);
        }

        return $this->json(['success' => true, 'data' => $data]);
    }

    private function serializeBooking(Booking $booking): array
    {
        return [
            'id' => $booking->getId(),
            'reference' => $booking->getReference(),
            'user' => [
                'id' => $booking->getUser()->getId(),
                'name' => $booking->getUser()->getFullName(),
                'email' => $booking->getUser()->getEmail(),
            ],
            'trip' => [
                'id' => $booking->getTrip()->getId(),
                'departureCity' => $booking->getTrip()->getDepartureCity(),
                'arrivalCity' => $booking->getTrip()->getArrivalCity(),
                'departureTime' => $booking->getTrip()->getDepartureTime()->format('Y-m-d H:i:s'),
                'agency' => [
                    'id' => $booking->getTrip()->getAgency()->getId(),
                    'name' => $booking->getTrip()->getAgency()->getName(),
                ],
            ],
            'status' => $booking->getStatus(),
            'totalPrice' => $booking->getTotalPrice(),
            'numberOfSeats' => $booking->getNumberOfSeats(),
            'seatNumbers' => $booking->getSeatNumbers(),
            'passengers' => $booking->getPassengers(),
            'specialRequests' => $booking->getSpecialRequests(),
            'createdAt' => $booking->getCreatedAt()->format('Y-m-d H:i:s'),
            'updatedAt' => $booking->getUpdatedAt()->format('Y-m-d H:i:s'),
            'cancelledAt' => $booking->getCancelledAt()?->format('Y-m-d H:i:s'),
        ];
    }
}
