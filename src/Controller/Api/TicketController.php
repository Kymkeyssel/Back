<?php

namespace App\Controller\Api;

use App\Entity\Ticket;
use App\Repository\BookingRepository;
use App\Repository\TicketRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class TicketController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TicketRepository $ticketRepository,
        private BookingRepository $bookingRepository,
        private ValidatorInterface $validator
    ) {
    }

    #[Route('/api/tickets', name: 'api_tickets', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'Authentication required.'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $tickets = $this->ticketRepository->findByUser($user->getId());

        $data = [];
        foreach ($tickets as $ticket) {
            $data[] = $this->serializeTicket($ticket);
        }

        return $this->json([
            'success' => true,
            'data' => $data
        ]);
    }

    #[Route('/api/tickets/{id}', name: 'api_ticket_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'Authentication required.'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $ticket = $this->ticketRepository->find($id);

        if (!$ticket) {
            return $this->json([
                'success' => false,
                'message' => 'Ticket not found.'
            ], Response::HTTP_NOT_FOUND);
        }

        // Check ownership
        if ($ticket->getUser() !== $user && !in_array('ROLE_ADMIN', $user->getRoles())) {
            return $this->json([
                'success' => false,
                'message' => 'You do not have permission to view this ticket.'
            ], Response::HTTP_FORBIDDEN);
        }

        return $this->json([
            'success' => true,
            'data' => $this->serializeTicket($ticket)
        ]);
    }

    #[Route('/api/tickets/{id}/scan', name: 'api_ticket_scan', methods: ['POST'])]
    public function scan(int $id): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'Authentication required.'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $ticket = $this->ticketRepository->find($id);

        if (!$ticket) {
            return $this->json([
                'success' => false,
                'message' => 'Ticket not found.'
            ], Response::HTTP_NOT_FOUND);
        }

        // Check if ticket is active
        if ($ticket->getStatus() !== 'active') {
            return $this->json([
                'success' => false,
                'message' => 'Ticket is not active.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Mark ticket as used
        $ticket->setStatus('used');
        $ticket->setScannedAt(new \DateTimeImmutable());

        // Save
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Ticket scanned successfully.',
            'data' => $this->serializeTicket($ticket)
        ]);
    }

    private function serializeTicket(Ticket $ticket): array
    {
        return [
            'id' => $ticket->getId(),
            'booking' => [
                'id' => $ticket->getBooking()->getId(),
                'reference' => $ticket->getBooking()->getReference(),
            ],
            'user' => [
                'id' => $ticket->getUser()->getId(),
                'name' => $ticket->getUser()->getFullName(),
            ],
            'qrCode' => $ticket->getQrCode(),
            'qrCodeData' => $ticket->getQrCodeData(),
            'status' => $ticket->getStatus(),
            'seatNumber' => $ticket->getSeatNumber(),
            'passengerName' => $ticket->getPassengerName(),
            'passengerPhone' => $ticket->getPassengerPhone(),
            'scannedAt' => $ticket->getScannedAt()?->format('Y-m-d H:i:s'),
            'createdAt' => $ticket->getCreatedAt()->format('Y-m-d H:i:s'),
            'updatedAt' => $ticket->getUpdatedAt()->format('Y-m-d H:i:s'),
        ];
    }
}
