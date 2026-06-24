<?php

namespace App\Service;

use App\Entity\Booking;
use App\Entity\Ticket;
use Doctrine\ORM\EntityManagerInterface;

class TicketIssuanceService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function ensureTicketsForConfirmedBooking(Booking $booking): void
    {
        if ($booking->getTickets()->count() > 0) {
            return;
        }
        if ($booking->getStatus() !== 'confirmed') {
            return;
        }

        $passengers = $booking->getPassengers();
        if ($passengers === []) {
            return;
        }

        $seats = $booking->getSeatNumbers();
        $i = 0;
        foreach ($passengers as $p) {
            $name = is_array($p) ? ($p['name'] ?? 'Passager') : 'Passager';
            $phone = is_array($p) ? ($p['phone'] ?? '') : '';
            $seatLabel = isset($seats[$i]) && is_string($seats[$i]) ? $seats[$i] : 'S'.($i + 1);

            $ticket = new Ticket();
            $ticket->setBooking($booking);
            $ticket->setUser($booking->getUser());
            $ticket->setQrCode('TKT-'.strtoupper(bin2hex(random_bytes(6))));
            $ticket->setQrCodeData([
                'bookingRef' => $booking->getReference(),
                'tripId' => $booking->getTrip()->getId(),
                'seatLabel' => $seatLabel,
            ]);
            $ticket->setStatus('active');
            $ticket->setSeatNumber($this->seatLabelToInt($seatLabel, $i));
            $ticket->setPassengerName($name);
            $ticket->setPassengerPhone($phone !== '' ? $phone : '000000000');
            $this->entityManager->persist($ticket);
            ++$i;
        }

        $this->entityManager->flush();
    }

    private function seatLabelToInt(string $label, int $fallbackIndex): int
    {
        if (preg_match('/^([A-Za-z])(\d+)$/', $label, $m)) {
            $row = ord(strtoupper($m[1])) - 64;
            $col = (int) $m[2];

            return min(99999, max(1, $row * 100 + $col));
        }

        return $fallbackIndex + 1;
    }
}
