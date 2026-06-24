<?php

namespace App\Command;

use App\Entity\Booking;
use App\Entity\Trip;
use App\Repository\BookingRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'transcam:send-reminders',
    description: 'Envoyer les rappels de voyage aux clients',
)]
class SendRemindersCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private NotificationService $notificationService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('TransCam - Envoi des rappels de voyage');

        // Trouver les réservations confirmées pour aujourd'hui + demain
        $bookings = $this->entityManager->getRepository(Booking::class)->findPendingReminders();
        
        $sentCount = 0;
        $skippedCount = 0;

        foreach ($bookings as $booking) {
            $user = $booking->getUser();
            $trip = $booking->getTrip();
            
            if ($user === null || $trip === null) {
                $skippedCount++;
                continue;
            }

            // Calculer les heures avant le départ
            $departureTime = $trip->getDepartureTime();
            $now = new \DateTimeImmutable();
            $hoursUntilDeparture = $departureTime->diff($now)->h;

            // Envoyer le rappel approprié
            if ($hoursUntilDeparture >= 24 && $hoursUntilDeparture <= 48) {
                // Rappel 24h avant
                $title = 'Rappel: Votre voyage demain';
                $message = sprintf(
                    'Rappel: Votre trajet %s → %s est prévu demain à %s. 
                    Veuillez arriver 30 minutes avant le départ.',
                    $trip->getDepartureCity(),
                    $trip->getArrivalCity(),
                    $departureTime->format('H:i')
                );
            } elseif ($hoursUntilDeparture >= 1 && $hoursUntilDeparture <= 3) {
                // Rappel 1-3h avant
                $title = 'Rappel: Départ imminent';
                $message = sprintf(
                    'Votre trajet %s → %s part dans %d heures. 
                    Le départ est prévu à %s.',
                    $trip->getDepartureCity(),
                    $trip->getArrivalCity(),
                    $hoursUntilDeparture,
                    $departureTime->format('H:i')
                );
            } else {
                $skippedCount++;
                continue;
            }

            try {
                $this->notificationService->createNotification(
                    $user,
                    'trip',
                    $title,
                    $message,
                    [
                        'bookingId' => $booking->getId(),
                        'tripId' => $trip->getId(),
                        'reference' => $booking->getReference(),
                    ]
                );
                $sentCount++;
            } catch (\Exception $e) {
                $io->warning(sprintf(
                    'Erreur pour la réservation %s: %s',
                    $booking->getReference(),
                    $e->getMessage()
                ));
            }
        }

        $io->success(sprintf(
            'Rappels envoyés: %d, Ignorés: %d',
            $sentCount,
            $skippedCount
        ));

        return Command::SUCCESS;
    }
}
