<?php

namespace App\EventSubscriber;

use App\Entity\Booking;
use App\Entity\Notification;
use App\Service\NotificationService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::postPersist, entity: Booking::class, method: 'onBookingCreated')]
#[AsEntityListener(event: Events::preUpdate, entity: Booking::class, method: 'onBookingPreUpdate')]
#[AsEntityListener(event: Events::postUpdate, entity: Booking::class, method: 'onBookingUpdated')]
class BookingEventSubscriber
{
    private ?array $previousValues = null;

    public function __construct(
        private NotificationService $notificationService
    ) {}

    /**
     * Stocker les valeurs précédentes avant mise à jour
     */
    public function onBookingPreUpdate(Booking $booking): void
    {
        // Stocker le statut actuel pour comparaison
        $this->previousValues = [
            'status' => $booking->getStatus(),
        ];
    }

    /**
     * Envoyer une notification après création d'une réservation
     */
    public function onBookingCreated(Booking $booking): void
    {
        $user = $booking->getUser();
        
        if ($user === null) {
            return;
        }

        $message = sprintf(
            'Votre réservation %s a été créée avec succès. %d place(s) pour le trajet %s → %s.',
            $booking->getReference(),
            $booking->getNumberOfSeats(),
            $booking->getTrip()->getDepartureCity(),
            $booking->getTrip()->getArrivalCity()
        );

        $this->notificationService->createNotification(
            $user,
            'booking',
            'Réservation créée',
            $message,
            [
                'bookingId' => $booking->getId(),
                'reference' => $booking->getReference(),
                'status' => $booking->getStatus(),
            ]
        );
    }

    /**
     * Envoyer une notification lors de la mise à jour d'une réservation
     */
    public function onBookingUpdated(Booking $booking): void
    {
        $user = $booking->getUser();
        
        if ($user === null || $this->previousValues === null) {
            return;
        }

        // Détecter le changement de statut
        $newStatus = $booking->getStatus();
        $oldStatus = $this->previousValues['status'] ?? null;
        
        if ($oldStatus !== $newStatus) {
            switch ($newStatus) {
                case 'confirmed':
                    $title = 'Réservation confirmée';
                    $message = sprintf(
                        'Votre réservation %s a été confirmée. Départ: %s',
                        $booking->getReference(),
                        $booking->getTrip()->getDepartureTime()->format('d/m/Y à H:i')
                    );
                    break;
                    
                case 'cancelled':
                    $title = 'Réservation annulée';
                    $message = sprintf(
                        'Votre réservation %s a été annulée. Le montant sera remboursé sous 5-10 jours.',
                        $booking->getReference()
                    );
                    $booking->setCancelledAt(new \DateTimeImmutable());
                    break;
                    
                case 'completed':
                    $title = 'Voyage terminé';
                    $message = sprintf(
                        'Merci d\'avoir voyagé avec TransCam! Votre réservation %s est terminée.',
                        $booking->getReference()
                    );
                    break;
                    
                default:
                    return;
            }

            $this->notificationService->createNotification(
                $user,
                'booking',
                $title,
                $message,
                [
                    'bookingId' => $booking->getId(),
                    'reference' => $booking->getReference(),
                    'status' => $newStatus,
                ]
            );
        }
        
        // Reset for next use
        $this->previousValues = null;
    }
}
