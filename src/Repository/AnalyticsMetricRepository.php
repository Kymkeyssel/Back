<?php

namespace App\Repository;

use App\Entity\AnalyticsDashboard;
use App\Entity\AnalyticsMetric;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AnalyticsMetric>
 */
class AnalyticsMetricRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AnalyticsMetric::class);
    }

    public function save(AnalyticsMetric $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(AnalyticsMetric $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find metrics by dashboard
     * @return AnalyticsMetric[]
     */
    public function findByDashboard(AnalyticsDashboard $dashboard): array
    {
        return $this->createQueryBuilder('am')
            ->andWhere('am.dashboard = :dashboard')
            ->setParameter('dashboard', $dashboard)
            ->orderBy('am.calculatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find metrics by dashboard and type
     * @return AnalyticsMetric[]
     */
    public function findByDashboardAndType(AnalyticsDashboard $dashboard, string $type): array
    {
        return $this->createQueryBuilder('am')
            ->andWhere('am.dashboard = :dashboard')
            ->andWhere('am.type = :type')
            ->setParameter('dashboard', $dashboard)
            ->setParameter('type', $type)
            ->orderBy('am.calculatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find latest metric by dashboard and name
     */
    public function findLatestByDashboardAndName(AnalyticsDashboard $dashboard, string $name): ?AnalyticsMetric
    {
        return $this->createQueryBuilder('am')
            ->andWhere('am.dashboard = :dashboard')
            ->andWhere('am.name = :name')
            ->setParameter('dashboard', $dashboard)
            ->setParameter('name', $name)
            ->orderBy('am.calculatedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find metrics by dashboard and period
     * @return AnalyticsMetric[]
     */
    public function findByDashboardAndPeriod(AnalyticsDashboard $dashboard, string $period): array
    {
        return $this->createQueryBuilder('am')
            ->andWhere('am.dashboard = :dashboard')
            ->andWhere('am.period = :period')
            ->setParameter('dashboard', $dashboard)
            ->setParameter('period', $period)
            ->orderBy('am.calculatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count metrics by dashboard
     */
    public function countByDashboard(AnalyticsDashboard $dashboard): int
    {
        return $this->createQueryBuilder('am')
            ->select('COUNT(am.id)')
            ->andWhere('am.dashboard = :dashboard')
            ->setParameter('dashboard', $dashboard)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get average value by dashboard and type
     */
    public function getAverageValueByDashboardAndType(AnalyticsDashboard $dashboard, string $type): float
    {
        $result = $this->createQueryBuilder('am')
            ->select('AVG(am.value)')
            ->andWhere('am.dashboard = :dashboard')
            ->andWhere('am.type = :type')
            ->setParameter('dashboard', $dashboard)
            ->setParameter('type', $type)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }
}
