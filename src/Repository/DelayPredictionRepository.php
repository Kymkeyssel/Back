<?php

namespace App\Repository;

use App\Entity\DelayPrediction;
use App\Entity\Trip;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DelayPrediction>
 */
class DelayPredictionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DelayPrediction::class);
    }

    public function save(DelayPrediction $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(DelayPrediction $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find predictions by trip
     * @return DelayPrediction[]
     */
    public function findByTrip(Trip $trip): array
    {
        return $this->createQueryBuilder('dp')
            ->andWhere('dp.trip = :trip')
            ->setParameter('trip', $trip)
            ->orderBy('dp.predictedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find latest prediction by trip
     */
    public function findLatestByTrip(Trip $trip): ?DelayPrediction
    {
        return $this->createQueryBuilder('dp')
            ->andWhere('dp.trip = :trip')
            ->setParameter('trip', $trip)
            ->orderBy('dp.predictedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find predictions by risk level
     * @return DelayPrediction[]
     */
    public function findByRiskLevel(string $riskLevel): array
    {
        return $this->createQueryBuilder('dp')
            ->andWhere('dp.riskLevel = :riskLevel')
            ->setParameter('riskLevel', $riskLevel)
            ->orderBy('dp.predictedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find accurate predictions
     * @return DelayPrediction[]
     */
    public function findAccuratePredictions(): array
    {
        return $this->createQueryBuilder('dp')
            ->andWhere('dp.isAccurate = :accurate')
            ->setParameter('accurate', true)
            ->orderBy('dp.predictedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count predictions by trip
     */
    public function countByTrip(Trip $trip): int
    {
        return $this->createQueryBuilder('dp')
            ->select('COUNT(dp.id)')
            ->andWhere('dp.trip = :trip')
            ->setParameter('trip', $trip)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get average confidence score by trip
     */
    public function getAverageConfidenceByTrip(Trip $trip): float
    {
        $result = $this->createQueryBuilder('dp')
            ->select('AVG(dp.confidenceScore)')
            ->andWhere('dp.trip = :trip')
            ->setParameter('trip', $trip)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    /**
     * Get accuracy rate
     */
    public function getAccuracyRate(): float
    {
        $total = $this->createQueryBuilder('dp')
            ->select('COUNT(dp.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $accurate = $this->createQueryBuilder('dp')
            ->select('COUNT(dp.id)')
            ->andWhere('dp.isAccurate = :accurate')
            ->setParameter('accurate', true)
            ->getQuery()
            ->getSingleScalarResult();

        return $total > 0 ? ($accurate / $total) * 100 : 0;
    }
}
