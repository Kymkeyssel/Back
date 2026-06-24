<?php

namespace App\EventSubscriber;

use App\Entity\Trip;
use App\Entity\Booking;
use App\Service\NotificationService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::preUpdate, entity: Trip::class, method: 'onTripPreUpdate')]
#[AsEntityListener(event: Events::postUpdate, entity: Trip::class, method: 'onTripUpdated')]
class TripEventSubscriber
{
    private ?array $previousValues = null;

    public function __construct(
        private NotificationService $notificationService
    ) {}

    public function onTripPreUpdate(Trip $trip): void
    {
        $this->previousValues = [
            'status' => $trip->getStatus(),
        ];
    }

    /**
     * Envoyer des notifications lorsque le statut d'un trajet change
     */
    public function onTripUpdated(Trip $trip): void
    {
        if ($this->previousValues === null) {
            return;
        }

        $newStatus = $trip->getStatus();
        $oldStatus = $this->previousValues['status'] ?? null;
        
        // Si le statut a changé, notifier tous les clients avec des réservations
        if ($oldStatus !== $newStatus) {
            $this->notifyBookings($trip, $oldStatus, $newStatus);
        }
        
        $this->previousValues = null;
    }

    /**
     *Notifier tous les clients ayant une réservation pour ce trajet
     */
    private function notifyBookings(Trip $trip, string $oldStatus, string $newStatus): void
    {
        $bookings = $trip->getBookings();
        
        foreach ($bookings as $booking) {
            $user = $booking->getUser();
            
            if ($user === null) {
                continue;
            }

            switch ($newStatus) {
                case 'in_progress':
                    $title = 'Trajet démarré';
                    $message = sprintf(
                        'Le trajet %s → %s a démarré. Bonne route!',
                        $trip->getDepartureCity(),
                        $trip->getArrivalCity()
                    );
                    break;
                    
                case 'completed':
                    $title = 'Trajet terminé';
                    $message = sprintf(
                        'Le trajet %s → %s est arrivé à destination. Merci d\'avoir voyagé avec TransCam!',
                        $trip->getDepartureCity(),
                        $trip->getArrivalCity()
                    );
                    break;
                    
                case 'cancelled':
                    $title = 'Trajet annulé';
                    $message = sprintf(
                        'Le trajet %s → %s prévu a été annulé. Votre réservation a été automatiquement annulée.',
                        $trip->getDepartureCity(),
                        $trip->getArrivalCity()
                    );
                    
                    // Annuler la réservation si pas encore confirmée
                    if ($booking->getStatus() === 'pending') {
                        $booking->setStatus('cancelled');
                        $booking->setCancelledAt(new \DateTimeImmutable());
                    }
                    break;
                    
                default:
                    return;
            }

            $this->notificationService->createNotification(
                $user,
                'trip',
                $title,
                $message,
                [
                    'tripId' => $trip->getId(),
                    'departureCity' => $trip->getDepartureCity(),
                    'arrivalCity' => $trip->getArrivalCity(),
                    'oldStatus' => $oldStatus,
                    'newStatus' => $newStatus,
                ]
            );
        }
    }
}
