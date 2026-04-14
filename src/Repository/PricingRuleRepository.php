<?php

namespace App\Repository;

use App\Entity\Agency;
use App\Entity\PricingRule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PricingRule>
 */
class PricingRuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PricingRule::class);
    }

    public function save(PricingRule $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(PricingRule $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find active rules by agency
     * @return PricingRule[]
     */
    public function findActiveByAgency(Agency $agency): array
    {
        return $this->createQueryBuilder('pr')
            ->andWhere('pr.agency = :agency OR pr.agency IS NULL')
            ->andWhere('pr.isActive = :active')
            ->setParameter('agency', $agency)
            ->setParameter('active', true)
            ->orderBy('pr.priority', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find active rules by type
     * @return PricingRule[]
     */
    public function findActiveByType(string $type): array
    {
        return $this->createQueryBuilder('pr')
            ->andWhere('pr.type = :type')
            ->andWhere('pr.isActive = :active')
            ->setParameter('type', $type)
            ->setParameter('active', true)
            ->orderBy('pr.priority', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all active rules
     * @return PricingRule[]
     */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('pr')
            ->andWhere('pr.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('pr.priority', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count rules by agency
     */
    public function countByAgency(Agency $agency): int
    {
        return $this->createQueryBuilder('pr')
            ->select('COUNT(pr.id)')
            ->andWhere('pr.agency = :agency')
            ->setParameter('agency', $agency)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
