<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

class NotificationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private NotificationRepository $notificationRepository,
        private HubInterface $hub,
        private FirebaseService $firebaseService
    ) {
    }

    /**
     * Create notification
     */
    public function createNotification(
        User $user,
        string $type,
        string $title,
        string $message,
        array $data = []
    ): Notification {
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setType($type);
        $notification->setTitle($title);
        $notification->setMessage($message);
        $notification->setData($data);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        // Send real-time notification via Mercure
        $this->sendRealTimeNotification($user, $notification);

        // Send push notification
        $this->sendPushNotification($user, $title, $message, $data);

        return $notification;
    }

    /**
     * Send booking confirmation notification
     */
    public function sendBookingConfirmation(User $user, array $bookingData): void
    {
        $this->createNotification(
            $user,
            'booking',
            'Réservation confirmée',
            sprintf(
                'Votre réservation %s pour le trajet %s → %s a été confirmée.',
                $bookingData['reference'],
                $bookingData['departureCity'],
                $bookingData['arrivalCity']
            ),
            $bookingData
        );
    }

    /**
     * Send payment confirmation notification
     */
    public function sendPaymentConfirmation(User $user, array $paymentData): void
    {
        $this->createNotification(
            $user,
            'payment',
            'Paiement effectué',
            sprintf(
                'Votre paiement de %s %s a été effectué avec succès.',
                number_format($paymentData['amount'], 0, ',', ' '),
                $paymentData['currency']
            ),
            $paymentData
        );
    }

    /**
     * Send trip reminder notification
     */
    public function sendTripReminder(User $user, array $tripData): void
    {
        $this->createNotification(
            $user,
            'trip',
            'Rappel de voyage',
            sprintf(
                'Votre trajet %s → %s départ dans 24 heures.',
                $tripData['departureCity'],
                $tripData['arrivalCity']
            ),
            $tripData
        );
    }

    /**
     * Send trip delay notification
     */
    public function sendTripDelay(User $user, array $tripData, int $delayMinutes): void
    {
        $this->createNotification(
            $user,
            'trip',
            'Retard de trajet',
            sprintf(
                'Votre trajet %s → %s est retardé de %d minutes.',
                $tripData['departureCity'],
                $tripData['arrivalCity'],
                $delayMinutes
            ),
            array_merge($tripData, ['delayMinutes' => $delayMinutes])
        );
    }

    /**
     * Send promotion notification
     */
    public function sendPromotion(User $user, string $title, string $message, array $promoData): void
    {
        $this->createNotification(
            $user,
            'promotion',
            $title,
            $message,
            $promoData
        );
    }

    /**
     * Send system notification
     */
    public function sendSystemNotification(User $user, string $title, string $message): void
    {
        $this->createNotification(
            $user,
            'system',
            $title,
            $message
        );
    }

    /**
     * Send real-time notification via Mercure
     */
    private function sendRealTimeNotification(User $user, Notification $notification): void
    {
        $update = new Update(
            sprintf('user/%d/notifications', $user->getId()),
            json_encode([
                'id' => $notification->getId(),
                'type' => $notification->getType(),
                'title' => $notification->getTitle(),
                'message' => $notification->getMessage(),
                'data' => $notification->getData(),
                'isRead' => $notification->isRead(),
                'createdAt' => $notification->getCreatedAt()->format('Y-m-d H:i:s'),
            ])
        );

        $this->hub->publish($update);
    }

    /**
     * Send push notification via Firebase
     */
    private function sendPushNotification(User $user, string $title, string $message, array $data = []): void
    {
        if (!method_exists($user, 'getFcmTokens')) {
            return;
        }

        $fcmTokens = $user->getFcmTokens() ?? [];

        if (!empty($fcmTokens)) {
            $this->firebaseService->sendMulticast(
                $fcmTokens,
                $title,
                $message,
                $data
            );
        }
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(Notification $notification): void
    {
        $notification->setIsRead(true);
        $notification->setReadAt(new \DateTimeImmutable());
        $this->entityManager->flush();
    }

    /**
     * Mark all notifications as read for user
     */
    public function markAllAsRead(User $user): void
    {
        $this->notificationRepository->markAllAsReadByUser($user->getId());
    }

    /**
     * Get unread notifications count
     */
    public function getUnreadCount(User $user): int
    {
        return $this->notificationRepository->countUnreadByUser($user->getId());
    }
}
