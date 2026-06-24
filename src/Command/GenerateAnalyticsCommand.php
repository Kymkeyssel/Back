<?php

namespace App\Command;

use App\Entity\AnalyticsMetric;
use App\Entity\Trip;
use App\Entity\Booking;
use App\Entity\Agency;
use App\Repository\TripRepository;
use App\Repository\BookingRepository;
use App\Repository\AgencyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'transcam:generate-analytics',
    description: 'Generer les metriques analytiques quotidiennes',
)]
class GenerateAnalyticsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('TransCam - Generation des analytiques');

        $now = new \DateTimeImmutable();
        $yesterday = $now->modify('-1 day');
        $metricsCreated = 0;

        // 1. Nombre de reservations hier
        $bookingsCount = $this->entityManager->getRepository(Booking::class)
            ->countBookingsForDate($yesterday);
        
        $this->createMetric(
            'daily_bookings',
            'bookings',
            $bookingsCount,
            'count',
            'daily'
        );
        $metricsCreated++;

        // 2. Revenu total hier
        $revenue = $this->entityManager->getRepository(Booking::class)
            ->getRevenueForDate($yesterday);
        
        $this->createMetric(
            'daily_revenue',
            'revenue',
            $revenue,
            'XAF',
            'daily'
        );
        $metricsCreated++;

        // 3. Trajets termines
        $tripsCompleted = $this->entityManager->getRepository(Trip::class)
            ->countCompletedTripsForDate($yesterday);
        
        $this->createMetric(
            'daily_completed_trips',
            'trips',
            $tripsCompleted,
            'count',
            'daily'
        );
        $metricsCreated++;

        // Sauvegarder
        $this->entityManager->flush();

        $io->success(sprintf('%d metrique(s) cree(s)', $metricsCreated));

        return Command::SUCCESS;
    }

    private function createMetric(
        string $name,
        string $type,
        mixed $value,
        ?string $unit = null,
        ?string $period = null
    ): void {
        $metric = new AnalyticsMetric();
        $metric->setName($name);
        $metric->setType($type);
        $metric->setValue($value);
        
        if ($unit) {
            $metric->setUnit($unit);
        }
        
        if ($period) {
            $metric->setPeriod($period);
        }
        
        $metric->setCalculatedAt(new \DateTimeImmutable());
        
        $this->entityManager->persist($metric);
    }
}
