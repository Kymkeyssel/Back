<?php

namespace App\Repository;

use App\Entity\Agency;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Agency>
 */
class AgencyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Agency::class);
    }

    public function save(Agency $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Agency $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findBySlug(string $slug): ?Agency
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    public function findActiveAgencies(): array
    {
        return $this->findBy(['isActive' => true], ['rating' => 'DESC']);
    }

    public function findVerifiedAgencies(): array
    {
        return $this->findBy(['isVerified' => true, 'isActive' => true], ['rating' => 'DESC']);
    }

    public function findByCity(string $city): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.city = :city')
            ->andWhere('a.isActive = :active')
            ->setParameter('city', $city)
            ->setParameter('active', true)
            ->orderBy('a.rating', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function searchAgencies(string $query): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.name LIKE :query OR a.city LIKE :query OR a.description LIKE :query')
            ->andWhere('a.isActive = :active')
            ->setParameter('query', '%' . $query . '%')
            ->setParameter('active', true)
            ->orderBy('a.rating', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
