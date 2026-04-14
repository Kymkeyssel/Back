<?php

namespace App\Repository;

use App\Entity\Vehicle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Vehicle>
 */
class VehicleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Vehicle::class);
    }

    public function save(Vehicle $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Vehicle $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByPlateNumber(string $plateNumber): ?Vehicle
    {
        return $this->findOneBy(['plateNumber' => $plateNumber]);
    }

    public function findActiveVehicles(): array
    {
        return $this->findBy(['isActive' => true]);
    }

    public function findByAgency(int $agencyId): array
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.agency = :agencyId')
            ->andWhere('v.isActive = :active')
            ->setParameter('agencyId', $agencyId)
            ->setParameter('active', true)
            ->orderBy('v.type', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.type = :type')
            ->andWhere('v.isActive = :active')
            ->setParameter('type', $type)
            ->setParameter('active', true)
            ->orderBy('v.brand', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findElectricVehicles(): array
    {
        return $this->findBy(['isElectric' => true, 'isActive' => true]);
    }
}
