<?php

namespace App\Service;

use App\Entity\Booking;
use App\Entity\Ticket;
use App\Entity\User;
use App\Repository\BookingRepository;
use App\Repository\TicketRepository;
use Doctrine\ORM\EntityManagerInterface;

class OfflineService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BookingRepository $bookingRepository,
        private TicketRepository $ticketRepository
    ) {
    }

    /**
     * Get offline data for user
     */
    public function getOfflineData(User $user): array
    {
        return [
            'bookings' => $this->getOfflineBookings($user),
            'tickets' => $this->getOfflineTickets($user),
            'lastSync' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Get offline bookings for user
     */
    private function getOfflineBookings(User $user): array
    {
        $bookings = $this->bookingRepository->findByUser($user->getId());
        
        $offlineBookings = [];
        foreach ($bookings as $booking) {
            $offlineBookings[] = [
                'id' => $booking->getId(),
                'reference' => $booking->getReference(),
                'trip' => [
                    'id' => $booking->getTrip()->getId(),
                    'departureCity' => $booking->getTrip()->getDepartureCity(),
                    'arrivalCity' => $booking->getTrip()->getArrivalCity(),
                    'departureTime' => $booking->getTrip()->getDepartureTime()->format('Y-m-d H:i:s'),
                    'arrivalTime' => $booking->getTrip()->getArrivalTime()?->format('Y-m-d H:i:s'),
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
                'createdAt' => $booking->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }

        return $offlineBookings;
    }

    /**
     * Get offline tickets for user
     */
    private function getOfflineTickets(User $user): array
    {
        $tickets = $this->ticketRepository->findByUser($user->getId());
        
        $offlineTickets = [];
        foreach ($tickets as $ticket) {
            $offlineTickets[] = [
                'id' => $ticket->getId(),
                'qrCode' => $ticket->getQrCode(),
                'qrCodeData' => $ticket->getQrCodeData(),
                'status' => $ticket->getStatus(),
                'seatNumber' => $ticket->getSeatNumber(),
                'passengerName' => $ticket->getPassengerName(),
                'passengerPhone' => $ticket->getPassengerPhone(),
                'booking' => [
                    'id' => $ticket->getBooking()->getId(),
                    'reference' => $ticket->getBooking()->getReference(),
                ],
                'trip' => [
                    'id' => $ticket->getBooking()->getTrip()->getId(),
                    'departureCity' => $ticket->getBooking()->getTrip()->getDepartureCity(),
                    'arrivalCity' => $ticket->getBooking()->getTrip()->getArrivalCity(),
                    'departureTime' => $ticket->getBooking()->getTrip()->getDepartureTime()->format('Y-m-d H:i:s'),
                    'agency' => [
                        'id' => $ticket->getBooking()->getTrip()->getAgency()->getId(),
                        'name' => $ticket->getBooking()->getTrip()->getAgency()->getName(),
                    ],
                ],
                'createdAt' => $ticket->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }

        return $offlineTickets;
    }

    /**
     * Sync offline data
     */
    public function syncOfflineData(User $user, array $offlineData): array
    {
        $syncedItems = [];
        $errors = [];

        // Sync bookings
        if (isset($offlineData['bookings'])) {
            foreach ($offlineData['bookings'] as $bookingData) {
                try {
                    $booking = $this->bookingRepository->find($bookingData['id']);
                    if ($booking && $booking->getUser()->getId() === $user->getId()) {
                        // Update booking status if changed
                        if ($bookingData['status'] !== $booking->getStatus()) {
                            $booking->setStatus($bookingData['status']);
                            $this->entityManager->flush();
                        }
                        $syncedItems[] = ['type' => 'booking', 'id' => $booking->getId()];
                    }
                } catch (\Exception $e) {
                    $errors[] = ['type' => 'booking', 'id' => $bookingData['id'], 'error' => $e->getMessage()];
                }
            }
        }

        // Sync tickets
        if (isset($offlineData['tickets'])) {
            foreach ($offlineData['tickets'] as $ticketData) {
                try {
                    $ticket = $this->ticketRepository->find($ticketData['id']);
                    if ($ticket && $ticket->getUser()->getId() === $user->getId()) {
                        // Update ticket status if changed
                        if ($ticketData['status'] !== $ticket->getStatus()) {
                            $ticket->setStatus($ticketData['status']);
                            $this->entityManager->flush();
                        }
                        $syncedItems[] = ['type' => 'ticket', 'id' => $ticket->getId()];
                    }
                } catch (\Exception $e) {
                    $errors[] = ['type' => 'ticket', 'id' => $ticketData['id'], 'error' => $e->getMessage()];
                }
            }
        }

        return [
            'success' => true,
            'syncedItems' => $syncedItems,
            'errors' => $errors,
            'syncedAt' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Check if user has offline data
     */
    public function hasOfflineData(User $user): bool
    {
        $bookings = $this->bookingRepository->findByUser($user->getId());
        $tickets = $this->ticketRepository->findByUser($user->getId());

        return !empty($bookings) || !empty($tickets);
    }

    /**
     * Get offline data size for user
     */
    public function getOfflineDataSize(User $user): int
    {
        $data = $this->getOfflineData($user);
        return strlen(json_encode($data));
    }
}
