<?php

namespace App\Repository;

use App\Entity\Agency;
use App\Entity\Booking;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Booking>
 */
class BookingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Booking::class);
    }

    public function save(Booking $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Booking $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByReference(string $reference): ?Booking
    {
        return $this->findOneBy(['reference' => $reference]);
    }

    public function findByUser(int $userId): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.user = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('b.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByTrip(int $tripId): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.trip = :tripId')
            ->andWhere('b.status != :cancelled')
            ->setParameter('tripId', $tripId)
            ->setParameter('cancelled', 'cancelled')
            ->orderBy('b.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Booking[]
     */
    public function findAllRecent(int $limit = 500): array
    {
        return $this->createQueryBuilder('b')
            ->orderBy('b.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findConfirmedBookings(): array
    {
        return $this->findBy(['status' => 'confirmed'], ['createdAt' => 'DESC']);
    }

    public function findPendingBookings(): array
    {
        return $this->findBy(['status' => 'pending'], ['createdAt' => 'DESC']);
    }

    public function findBookingsByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.createdAt BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('b.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countBookingsByStatus(): array
    {
        return $this->createQueryBuilder('b')
            ->select('b.status, COUNT(b.id) as count')
            ->groupBy('b.status')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count bookings for a specific date
     */
    public function countBookingsForDate(\DateTimeInterface $date): int
    {
        return $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->andWhere('DATE(b.createdAt) = :date')
            ->setParameter('date', $date->format('Y-m-d'))
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get revenue for a specific date
     */
    public function getRevenueForDate(\DateTimeInterface $date): float
    {
        $result = $this->createQueryBuilder('b')
            ->select('COALESCE(SUM(b.totalPrice), 0)')
            ->andWhere('b.status = :status')
            ->andWhere('DATE(b.createdAt) = :date')
            ->setParameter('status', 'confirmed')
            ->setParameter('date', $date->format('Y-m-d'))
            ->getQuery()
            ->getSingleScalarResult();
        
        return (float) $result;
    }

    /**
     * Count bookings by agency and date range
     */
    public function countBookingsByAgencyAndDateRange(Agency $agency, \DateTimeInterface $startDate, \DateTimeInterface $endDate): int
    {
        return $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->andWhere('b.trip = :agency')
            ->andWhere('b.createdAt BETWEEN :startDate AND :endDate')
            ->setParameter('agency', $agency)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findByAgency(int $agencyId): array
    {
        return $this->createQueryBuilder('b')
            ->join('b.trip', 't')
            ->andWhere('t.agency = :agencyId')
            ->setParameter('agencyId', $agencyId)
            ->orderBy('b.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get top routes by agency
     */
    public function getTopRoutesByAgency(Agency $agency, int $limit = 10): array
    {
        return $this->createQueryBuilder('b')
            ->select('t.departureCity as departureCity, t.arrivalCity as arrivalCity, COUNT(b.id) as bookingCount, SUM(b.totalPrice) as totalRevenue')
            ->join('b.trip', 't')
            ->andWhere('t.agency = :agency')
            ->groupBy('t.departureCity, t.arrivalCity')
            ->orderBy('bookingCount', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('agency', $agency)
            ->getQuery()
            ->getResult();
    }
}
