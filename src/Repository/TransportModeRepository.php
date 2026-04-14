<?php

namespace App\Repository;

use App\Entity\TransportMode;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TransportMode>
 */
class TransportModeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TransportMode::class);
    }

    public function save(TransportMode $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(TransportMode $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find active transport modes
     * @return TransportMode[]
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('t.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find transport mode by code
     */
    public function findByCode(string $code): ?TransportMode
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.code = :code')
            ->setParameter('code', $code)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find transport modes with trips
     * @return TransportMode[]
     */
    public function findWithTrips(): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.trips', 'trips')
            ->andWhere('t.isActive = :active')
            ->andWhere('SIZE(trips) > 0')
            ->setParameter('active', true)
            ->orderBy('t.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
