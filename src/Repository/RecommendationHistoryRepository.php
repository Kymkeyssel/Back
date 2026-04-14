<?php

namespace App\Repository;

use App\Entity\RecommendationHistory;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RecommendationHistory>
 */
class RecommendationHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RecommendationHistory::class);
    }

    public function save(RecommendationHistory $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(RecommendationHistory $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find recommendations by user
     * @return RecommendationHistory[]
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('rh')
            ->andWhere('rh.user = :user')
            ->setParameter('user', $user)
            ->orderBy('rh.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find recommendations by user and type
     * @return RecommendationHistory[]
     */
    public function findByUserAndType(User $user, string $type): array
    {
        return $this->createQueryBuilder('rh')
            ->andWhere('rh.user = :user')
            ->andWhere('rh.type = :type')
            ->setParameter('user', $user)
            ->setParameter('type', $type)
            ->orderBy('rh.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find clicked recommendations by user
     * @return RecommendationHistory[]
     */
    public function findClickedByUser(User $user): array
    {
        return $this->createQueryBuilder('rh')
            ->andWhere('rh.user = :user')
            ->andWhere('rh.wasClicked = :clicked')
            ->setParameter('user', $user)
            ->setParameter('clicked', true)
            ->orderBy('rh.clickedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find booked recommendations by user
     * @return RecommendationHistory[]
     */
    public function findBookedByUser(User $user): array
    {
        return $this->createQueryBuilder('rh')
            ->andWhere('rh.user = :user')
            ->andWhere('rh.wasBooked = :booked')
            ->setParameter('user', $user)
            ->setParameter('booked', true)
            ->orderBy('rh.bookedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count recommendations by user
     */
    public function countByUser(User $user): int
    {
        return $this->createQueryBuilder('rh')
            ->select('COUNT(rh.id)')
            ->andWhere('rh.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count clicked recommendations by user
     */
    public function countClickedByUser(User $user): int
    {
        return $this->createQueryBuilder('rh')
            ->select('COUNT(rh.id)')
            ->andWhere('rh.user = :user')
            ->andWhere('rh.wasClicked = :clicked')
            ->setParameter('user', $user)
            ->setParameter('clicked', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count booked recommendations by user
     */
    public function countBookedByUser(User $user): int
    {
        return $this->createQueryBuilder('rh')
            ->select('COUNT(rh.id)')
            ->andWhere('rh.user = :user')
            ->andWhere('rh.wasBooked = :booked')
            ->setParameter('user', $user)
            ->setParameter('booked', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get average relevance score by user
     */
    public function getAverageRelevanceScoreByUser(User $user): float
    {
        $result = $this->createQueryBuilder('rh')
            ->select('AVG(rh.relevanceScore)')
            ->andWhere('rh.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    /**
     * Get click-through rate by user
     */
    public function getClickThroughRateByUser(User $user): float
    {
        $total = $this->countByUser($user);
        $clicked = $this->countClickedByUser($user);

        return $total > 0 ? ($clicked / $total) * 100 : 0;
    }

    /**
     * Get conversion rate by user
     */
    public function getConversionRateByUser(User $user): float
    {
        $total = $this->countByUser($user);
        $booked = $this->countBookedByUser($user);

        return $total > 0 ? ($booked / $total) * 100 : 0;
    }
}
