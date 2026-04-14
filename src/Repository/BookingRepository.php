<?php

namespace App\Repository;

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
}
