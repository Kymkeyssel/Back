<?php

namespace App\Command;

use App\Entity\Trip;
use App\Entity\Booking;
use App\Repository\TripRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'transcam:update-trip-status',
    description: 'Mettre à jour automatiquement le statut des trajets',
)]
class UpdateTripStatusCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('TransCam - Mise à jour du statut des trajets');

        $now = new \DateTimeImmutable();
        $updatedCount = 0;

        // 1. Passer les trajets "scheduled" en "in_progress" si le départ a eu lieu
        $tripsToStart = $this->entityManager->getRepository(Trip::class)->findTripsToStart($now);
        
        foreach ($tripsToStart as $trip) {
            $trip->setStatus('in_progress');
            $updatedCount++;
            $io->writeln(sprintf(
                'Trajet #%d: %s → %s démarré',
                $trip->getId(),
                $trip->getDepartureCity(),
                $trip->getArrivalCity()
            ));
        }

        // 2. Passer les trajets "in_progress" en "completed" si l'arrivée a eu lieu
        $tripsToComplete = $this->entityManager->getRepository(Trip::class)->findTripsToComplete($now);
        
        foreach ($tripsToComplete as $trip) {
            $trip->setStatus('completed');
            $updatedCount++;
            $io->writeln(sprintf(
                'Trajet #%d: %s → %s terminé',
                $trip->getId(),
                $trip->getDepartureCity(),
                $trip->getArrivalCity()
            ));
        }

        // 3. Annuler les trajets dont le départ était prévu il y a plus de 2h
        $delayedTime = $now->modify('-2 hours');
        $tripsToCancel = $this->entityManager->getRepository(Trip::class)->findTripsToCancel($delayedTime);
        
        foreach ($tripsToCancel as $trip) {
            $trip->setStatus('cancelled');
            $updatedCount++;
            $io->writeln(sprintf(
                'Trajet #%d: %s → %s annulé (retard excessif)',
                $trip->getId(),
                $trip->getDepartureCity(),
                $trip->getArrivalCity()
            ));
        }

        // Sauvegarder les changements
        $this->entityManager->flush();

        $io->success(sprintf('%d trajet(s) mis à jour(s)', $updatedCount));

        return Command::SUCCESS;
    }
}
