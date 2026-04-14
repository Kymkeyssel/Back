<?php

namespace App\Repository;

use App\Entity\MultiModalTrip;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MultiModalTrip>
 */
class MultiModalTripRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MultiModalTrip::class);
    }

    public function save(MultiModalTrip $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(MultiModalTrip $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find multi-modal trips by user
     * @return MultiModalTrip[]
     */
    public function findByUser(int $userId): array
    {
        return $this->createQueryBuilder('mmt')
            ->andWhere('mmt.user = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('mmt.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find multi-modal trips by status
     * @return MultiModalTrip[]
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('mmt')
            ->andWhere('mmt.status = :status')
            ->setParameter('status', $status)
            ->orderBy('mmt.departureTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find multi-modal trips by departure city and arrival city
     * @return MultiModalTrip[]
     */
    public function findByRoute(string $departureCity, string $arrivalCity): array
    {
        return $this->createQueryBuilder('mmt')
            ->andWhere('mmt.departureCity = :departureCity')
            ->andWhere('mmt.arrivalCity = :arrivalCity')
            ->setParameter('departureCity', $departureCity)
            ->setParameter('arrivalCity', $arrivalCity)
            ->orderBy('mmt.departureTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find upcoming multi-modal trips
     * @return MultiModalTrip[]
     */
    public function findUpcoming(): array
    {
        return $this->createQueryBuilder('mmt')
            ->andWhere('mmt.departureTime > :now')
            ->andWhere('mmt.status IN (:statuses)')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('statuses', ['planned', 'booked'])
            ->orderBy('mmt.departureTime', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
