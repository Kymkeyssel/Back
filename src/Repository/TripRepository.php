<?php

namespace App\Repository;

use App\Entity\Trip;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Trip>
 */
class TripRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Trip::class);
    }

    public function save(Trip $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Trip $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findScheduledTrips(): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.status = :status')
            ->andWhere('t.departureTime > :now')
            ->andWhere('t.availableSeats > 0')
            ->setParameter('status', 'scheduled')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('t.departureTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function searchTrips(string $departureCity, string $arrivalCity, \DateTimeInterface $date): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.departureCity = :departureCity')
            ->andWhere('t.arrivalCity = :arrivalCity')
            ->andWhere('t.status = :status')
            ->andWhere('t.availableSeats > 0')
            ->andWhere('DATE(t.departureTime) = :date')
            ->setParameter('departureCity', $departureCity)
            ->setParameter('arrivalCity', $arrivalCity)
            ->setParameter('status', 'scheduled')
            ->setParameter('date', $date->format('Y-m-d'))
            ->orderBy('t.departureTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByAgency(int $agencyId): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.agency = :agencyId')
            ->setParameter('agencyId', $agencyId)
            ->orderBy('t.departureTime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findTripsByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.departureTime BETWEEN :startDate AND :endDate')
            ->andWhere('t.status = :status')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('status', 'scheduled')
            ->orderBy('t.departureTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findPopularRoutes(int $limit = 10): array
    {
        return $this->createQueryBuilder('t')
            ->select('t.departureCity, t.arrivalCity, COUNT(t.id) as tripCount')
            ->andWhere('t.status = :status')
            ->groupBy('t.departureCity, t.arrivalCity')
            ->setParameter('status', 'completed')
            ->orderBy('tripCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
