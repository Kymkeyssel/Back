<?php

namespace App\EventSubscriber;

use App\Entity\Payment;
use App\Service\NotificationService;
use App\Service\TicketIssuanceService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::postPersist, entity: Payment::class, method: 'onPaymentCreated')]
#[AsEntityListener(event: Events::preUpdate, entity: Payment::class, method: 'onPaymentPreUpdate')]
#[AsEntityListener(event: Events::postUpdate, entity: Payment::class, method: 'onPaymentUpdated')]
class PaymentEventSubscriber
{
    private \SplObjectStorage $previousStatuses;

    public function __construct(
        private NotificationService $notificationService,
        private TicketIssuanceService $ticketIssuanceService
    ) {
        $this->previousStatuses = new \SplObjectStorage();
    }

    public function onPaymentPreUpdate(Payment $payment): void
    {
        $this->previousStatuses[$payment] = $payment->getStatus();
    }

    public function onPaymentCreated(Payment $payment): void
    {
        $user = $payment->getUser();

        if ($user === null) {
            return;
        }

        $message = sprintf(
            'Votre paiement de %s %s est en cours de traitement. Référence: %s',
            $payment->getAmount(),
            $payment->getCurrency(),
            $payment->getTransactionId()
        );

        $this->notificationService->createNotification(
            $user,
            'payment',
            'Paiement initiated',
            $message,
            [
                'paymentId' => $payment->getId(),
                'transactionId' => $payment->getTransactionId(),
                'status' => $payment->getStatus(),
            ]
        );
    }

    public function onPaymentUpdated(Payment $payment): void
    {
        $user = $payment->getUser();
        $oldStatus = $this->previousStatuses[$payment] ?? null;
        unset($this->previousStatuses[$payment]);

        if ($user === null || $oldStatus === null) {
            return;
        }

        $newStatus = $payment->getStatus();
        if ($oldStatus === $newStatus) {
            return;
        }

        switch ($newStatus) {
            case Payment::STATUS_COMPLETED:
                $title = 'Paiement confirmé';
                $message = sprintf(
                    'Votre paiement de %s %s a été confirmé. Votre réservation est désormais active!',
                    $payment->getAmount(),
                    $payment->getCurrency()
                );

                $booking = $payment->getBooking();
                if ($booking !== null && $booking->getStatus() === 'pending') {
                    $booking->setStatus('confirmed');
                    $this->ticketIssuanceService->ensureTicketsForConfirmedBooking($booking);
                }
                break;

            case Payment::STATUS_FAILED:
                $title = 'Paiement échoué';
                $message = sprintf(
                    'Votre paiement de %s %s a échoué. Veuillez réessayer ou contacter le support.',
                    $payment->getAmount(),
                    $payment->getCurrency()
                );
                break;

            case Payment::STATUS_REFUNDED:
                $title = 'Paiement remboursé';
                $message = sprintf(
                    'Votre paiement de %s %s a été remboursé. Le montant devrait arriver sous 5-10 jours.',
                    $payment->getAmount(),
                    $payment->getCurrency()
                );

                $booking = $payment->getBooking();
                if ($booking !== null) {
                    $booking->setStatus('cancelled');
                    $booking->setCancelledAt(new \DateTimeImmutable());
                }
                break;

            default:
                return;
        }

        $this->notificationService->createNotification(
            $user,
            'payment',
            $title,
            $message,
            [
                'paymentId' => $payment->getId(),
                'transactionId' => $payment->getTransactionId(),
                'status' => $newStatus,
            ]
        );
    }
}
