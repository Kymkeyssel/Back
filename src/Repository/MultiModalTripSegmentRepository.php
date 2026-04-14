<?php

namespace App\Repository;

use App\Entity\MultiModalTripSegment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MultiModalTripSegment>
 */
class MultiModalTripSegmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MultiModalTripSegment::class);
    }

    public function save(MultiModalTripSegment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(MultiModalTripSegment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find segments by multi-modal trip
     * @return MultiModalTripSegment[]
     */
    public function findByMultiModalTrip(int $multiModalTripId): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.multiModalTrip = :multiModalTripId')
            ->setParameter('multiModalTripId', $multiModalTripId)
            ->orderBy('s.segmentOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find segments by transport mode
     * @return MultiModalTripSegment[]
     */
    public function findByTransportMode(int $transportModeId): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.transportMode = :transportModeId')
            ->setParameter('transportModeId', $transportModeId)
            ->orderBy('s.departureTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find segments by status
     * @return MultiModalTripSegment[]
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.status = :status')
            ->setParameter('status', $status)
            ->orderBy('s.departureTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find upcoming segments
     * @return MultiModalTripSegment[]
     */
    public function findUpcoming(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.departureTime > :now')
            ->andWhere('s.status IN (:statuses)')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('statuses', ['pending', 'confirmed'])
            ->orderBy('s.departureTime', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
