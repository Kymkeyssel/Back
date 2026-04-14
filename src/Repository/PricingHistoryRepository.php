<?php

namespace App\Repository;

use App\Entity\PricingHistory;
use App\Entity\Trip;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PricingHistory>
 */
class PricingHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PricingHistory::class);
    }

    public function save(PricingHistory $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(PricingHistory $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find pricing history by trip
     * @return PricingHistory[]
     */
    public function findByTrip(Trip $trip): array
    {
        return $this->createQueryBuilder('ph')
            ->andWhere('ph.trip = :trip')
            ->setParameter('trip', $trip)
            ->orderBy('ph.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find latest pricing history by trip
     */
    public function findLatestByTrip(Trip $trip): ?PricingHistory
    {
        return $this->createQueryBuilder('ph')
            ->andWhere('ph.trip = :trip')
            ->setParameter('trip', $trip)
            ->orderBy('ph.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find applied pricing history by trip
     * @return PricingHistory[]
     */
    public function findAppliedByTrip(Trip $trip): array
    {
        return $this->createQueryBuilder('ph')
            ->andWhere('ph.trip = :trip')
            ->andWhere('ph.status = :status')
            ->setParameter('trip', $trip)
            ->setParameter('status', 'applied')
            ->orderBy('ph.appliedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count pricing history by trip
     */
    public function countByTrip(Trip $trip): int
    {
        return $this->createQueryBuilder('ph')
            ->select('COUNT(ph.id)')
            ->andWhere('ph.trip = :trip')
            ->setParameter('trip', $trip)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get average multiplier by trip
     */
    public function getAverageMultiplierByTrip(Trip $trip): float
    {
        $result = $this->createQueryBuilder('ph')
            ->select('AVG(ph.multiplier)')
            ->andWhere('ph.trip = :trip')
            ->andWhere('ph.status = :status')
            ->setParameter('trip', $trip)
            ->setParameter('status', 'applied')
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 1.0);
    }
}
