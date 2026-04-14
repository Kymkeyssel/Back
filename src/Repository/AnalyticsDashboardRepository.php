<?php

namespace App\Repository;

use App\Entity\Agency;
use App\Entity\AnalyticsDashboard;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AnalyticsDashboard>
 */
class AnalyticsDashboardRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AnalyticsDashboard::class);
    }

    public function save(AnalyticsDashboard $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(AnalyticsDashboard $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find dashboards by user
     * @return AnalyticsDashboard[]
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('ad')
            ->andWhere('ad.user = :user OR ad.isPublic = :public')
            ->setParameter('user', $user)
            ->setParameter('public', true)
            ->orderBy('ad.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find dashboards by agency
     * @return AnalyticsDashboard[]
     */
    public function findByAgency(Agency $agency): array
    {
        return $this->createQueryBuilder('ad')
            ->andWhere('ad.agency = :agency')
            ->setParameter('agency', $agency)
            ->orderBy('ad.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find default dashboard by type
     */
    public function findDefaultByType(string $type): ?AnalyticsDashboard
    {
        return $this->createQueryBuilder('ad')
            ->andWhere('ad.type = :type')
            ->andWhere('ad.isDefault = :default')
            ->setParameter('type', $type)
            ->setParameter('default', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find dashboard by user and id
     */
    public function findByUserAndId(User $user, int $id): ?AnalyticsDashboard
    {
        return $this->createQueryBuilder('ad')
            ->andWhere('ad.id = :id')
            ->andWhere('ad.user = :user OR ad.isPublic = :public')
            ->setParameter('id', $id)
            ->setParameter('user', $user)
            ->setParameter('public', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Count dashboards by user
     */
    public function countByUser(User $user): int
    {
        return $this->createQueryBuilder('ad')
            ->select('COUNT(ad.id)')
            ->andWhere('ad.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
