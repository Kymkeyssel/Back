<?php

namespace App\Repository;

use App\Entity\DelayHistory;
use App\Entity\Trip;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DelayHistory>
 */
class DelayHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DelayHistory::class);
    }

    public function save(DelayHistory $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(DelayHistory $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find delay history by trip
     * @return DelayHistory[]
     */
    public function findByTrip(Trip $trip): array
    {
        return $this->createQueryBuilder('dh')
            ->andWhere('dh.trip = :trip')
            ->setParameter('trip', $trip)
            ->orderBy('dh.occurredAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find delay history by trip and date range
     * @return DelayHistory[]
     */
    public function findByTripAndDateRange(Trip $trip, \DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array
    {
        return $this->createQueryBuilder('dh')
            ->andWhere('dh.trip = :trip')
            ->andWhere('dh.occurredAt BETWEEN :startDate AND :endDate')
            ->setParameter('trip', $trip)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('dh.occurredAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find delay history by reason
     * @return DelayHistory[]
     */
    public function findByReason(string $reason): array
    {
        return $this->createQueryBuilder('dh')
            ->andWhere('dh.reason = :reason')
            ->setParameter('reason', $reason)
            ->orderBy('dh.occurredAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count delays by trip
     */
    public function countByTrip(Trip $trip): int
    {
        return $this->createQueryBuilder('dh')
            ->select('COUNT(dh.id)')
            ->andWhere('dh.trip = :trip')
            ->setParameter('trip', $trip)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get average delay by trip
     */
    public function getAverageDelayByTrip(Trip $trip): float
    {
        $result = $this->createQueryBuilder('dh')
            ->select('AVG(dh.delayMinutes)')
            ->andWhere('dh.trip = :trip')
            ->setParameter('trip', $trip)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    /**
     * Get total delay by trip
     */
    public function getTotalDelayByTrip(Trip $trip): int
    {
        $result = $this->createQueryBuilder('dh')
            ->select('SUM(dh.delayMinutes)')
            ->andWhere('dh.trip = :trip')
            ->setParameter('trip', $trip)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }

    /**
     * Get most common delay reasons
     */
    public function getMostCommonReasons(int $limit = 10): array
    {
        return $this->createQueryBuilder('dh')
            ->select('dh.reason, COUNT(dh.id) as reasonCount')
            ->andWhere('dh.reason IS NOT NULL')
            ->groupBy('dh.reason')
            ->orderBy('reasonCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
