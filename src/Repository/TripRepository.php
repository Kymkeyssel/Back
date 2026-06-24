<?php

namespace App\Repository;

use App\Domain\TransportScope;
use App\Entity\Agency;
use App\Entity\Trip;
use App\Entity\User;
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
            ->innerJoin('t.transportMode', 'tm')
            ->andWhere('tm.code IN (:modeCodes)')
            ->andWhere('t.status IN (:statuses)')
            ->andWhere('t.availableSeats > 0')
            ->setParameter('modeCodes', TransportScope::OFFER_TYPE_CODES)
            ->setParameter('statuses', ['scheduled', 'in_progress'])
            ->orderBy('t.departureTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function searchTrips(string $departureCity, string $arrivalCity, \DateTimeInterface $date, ?string $transportModeCode = null): array
    {
        $modeCodes = null !== $transportModeCode && in_array($transportModeCode, TransportScope::OFFER_TYPE_CODES, true)
            ? [$transportModeCode]
            : TransportScope::OFFER_TYPE_CODES;

        return $this->createQueryBuilder('t')
            ->innerJoin('t.transportMode', 'tm')
            ->andWhere('tm.code IN (:modeCodes)')
            ->andWhere('LOWER(t.departureCity) = LOWER(:departureCity)')
            ->andWhere('LOWER(t.arrivalCity) = LOWER(:arrivalCity)')
            ->andWhere('t.status = :status')
            ->andWhere('t.availableSeats > 0')
            ->andWhere('DATE(t.departureTime) = :date')
            ->setParameter('modeCodes', $modeCodes)
            ->setParameter('departureCity', $departureCity)
            ->setParameter('arrivalCity', $arrivalCity)
            ->setParameter('status', 'scheduled')
            ->setParameter('date', $date->format('Y-m-d'))
            ->orderBy('t.departureTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Intercity bus or carpool trips from A to B, departing on or after $minDepartureTime (same calendar day or later).
     */
    public function findTripsByRoute(string $departureCity, string $arrivalCity, \DateTimeInterface $minDepartureTime): array
    {
        return $this->createQueryBuilder('t')
            ->innerJoin('t.transportMode', 'tm')
            ->andWhere('tm.code IN (:modeCodes)')
            ->andWhere('t.departureCity = :departureCity')
            ->andWhere('t.arrivalCity = :arrivalCity')
            ->andWhere('t.status = :status')
            ->andWhere('t.availableSeats > 0')
            ->andWhere('t.departureTime >= :minDepartureTime')
            ->setParameter('modeCodes', TransportScope::OFFER_TYPE_CODES)
            ->setParameter('departureCity', $departureCity)
            ->setParameter('arrivalCity', $arrivalCity)
            ->setParameter('status', 'scheduled')
            ->setParameter('minDepartureTime', $minDepartureTime)
            ->orderBy('t.departureTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trips leaving from a city after a given time (for connection planning).
     *
     * @return Trip[]
     */
    public function findTripsFromCity(string $departureCity, \DateTimeInterface $minDepartureTime): array
    {
        return $this->createQueryBuilder('t')
            ->innerJoin('t.transportMode', 'tm')
            ->andWhere('tm.code IN (:modeCodes)')
            ->andWhere('t.departureCity = :departureCity')
            ->andWhere('t.status = :status')
            ->andWhere('t.availableSeats > 0')
            ->andWhere('t.departureTime >= :minDepartureTime')
            ->setParameter('modeCodes', TransportScope::OFFER_TYPE_CODES)
            ->setParameter('departureCity', $departureCity)
            ->setParameter('status', 'scheduled')
            ->setParameter('minDepartureTime', $minDepartureTime)
            ->orderBy('t.departureTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Trip[]
     */
    public function findTripsForDriver(User $driver): array
    {
        return $this->createQueryBuilder('t')
            ->innerJoin('t.vehicle', 'v')
            ->andWhere('v.driver = :driver')
            ->andWhere('t.status IN (:statuses)')
            ->setParameter('driver', $driver)
            ->setParameter('statuses', ['scheduled', 'in_progress'])
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

    /**
     * Find trips that should start (scheduled and departure time passed)
     */
    public function findTripsToStart(\DateTimeInterface $now): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.status = :status')
            ->andWhere('t.departureTime <= :now')
            ->setParameter('status', 'scheduled')
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find trips that should complete (in_progress and arrival time passed)
     */
    public function findTripsToComplete(\DateTimeInterface $now): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.status = :status')
            ->andWhere('t.arrivalTime <= :now')
            ->setParameter('status', 'in_progress')
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find trips to cancel (scheduled but departure delayed more than 2 hours)
     */
    public function findTripsToCancel(\DateTimeInterface $now): array
    {
        $delayedTime = (new \DateTimeImmutable())->modify('-2 hours');
        return $this->createQueryBuilder('t')
            ->andWhere('t.status = :status')
            ->andWhere('t.departureTime < :delayedTime')
            ->setParameter('status', 'scheduled')
            ->setParameter('delayedTime', $delayedTime)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count completed trips for a specific date
     */
    public function countCompletedTripsForDate(\DateTimeInterface $date): int
    {
        return $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.status = :status')
            ->andWhere('DATE(t.arrivalTime) = :date')
            ->setParameter('status', 'completed')
            ->setParameter('date', $date->format('Y-m-d'))
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count trips by agency and date range
     */
    public function countTripsByAgencyAndDateRange(Agency $agency, \DateTimeInterface $startDate, \DateTimeInterface $endDate): int
    {
        return $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.agency = :agency')
            ->andWhere('t.departureTime BETWEEN :startDate AND :endDate')
            ->setParameter('agency', $agency)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find trips by agency and date range
     */
    public function findByAgencyAndDateRange(Agency $agency, \DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.agency = :agency')
            ->andWhere('t.departureTime BETWEEN :startDate AND :endDate')
            ->setParameter('agency', $agency)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getResult();
    }

    public function findBestPricesByMode(string $modeCode, int $limit = 6): array
    {
        return $this->createQueryBuilder('t')
            ->innerJoin('t.transportMode', 'tm')
            ->andWhere('tm.code = :modeCode')
            ->andWhere('t.status = :status')
            ->andWhere('t.availableSeats > 0')
            ->setParameter('modeCode', $modeCode)
            ->setParameter('status', 'scheduled')
            ->orderBy('t.price', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findCarpoolTrips(): array
    {
        return $this->createQueryBuilder('t')
            ->innerJoin('t.transportMode', 'tm')
            ->andWhere('tm.code = :modeCode')
            ->andWhere('t.status IN (:statuses)')
            ->andWhere('t.availableSeats > 0')
            ->setParameter('modeCode', TransportScope::CARPOOL)
            ->setParameter('statuses', ['scheduled', 'in_progress'])
            ->orderBy('t.departureTime', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
