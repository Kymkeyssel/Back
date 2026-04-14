<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserPreference;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserPreference>
 */
class UserPreferenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserPreference::class);
    }

    public function save(UserPreference $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(UserPreference $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find preference by user
     */
    public function findByUser(User $user): ?UserPreference
    {
        return $this->createQueryBuilder('up')
            ->andWhere('up.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find users by preferred route
     * @return UserPreference[]
     */
    public function findByPreferredRoute(string $departureCity, string $arrivalCity): array
    {
        return $this->createQueryBuilder('up')
            ->andWhere('JSON_CONTAINS(up.preferredRoutes, :route) = 1')
            ->setParameter('route', json_encode(['departure' => $departureCity, 'arrival' => $arrivalCity]))
            ->getQuery()
            ->getResult();
    }

    /**
     * Find users by preferred agency
     * @return UserPreference[]
     */
    public function findByPreferredAgency(int $agencyId): array
    {
        return $this->createQueryBuilder('up')
            ->andWhere('JSON_CONTAINS(up.preferredAgencies, :agencyId) = 1')
            ->setParameter('agencyId', json_encode($agencyId))
            ->getQuery()
            ->getResult();
    }

    /**
     * Find users by budget range
     * @return UserPreference[]
     */
    public function findByBudgetRange(string $budgetRange): array
    {
        return $this->createQueryBuilder('up')
            ->andWhere('up.budgetRange = :budgetRange')
            ->setParameter('budgetRange', $budgetRange)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find eco-friendly users
     * @return UserPreference[]
     */
    public function findEcoFriendlyUsers(): array
    {
        return $this->createQueryBuilder('up')
            ->andWhere('up.prefersEcoFriendly = :ecoFriendly')
            ->setParameter('ecoFriendly', true)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count preferences by user
     */
    public function countByUser(User $user): int
    {
        return $this->createQueryBuilder('up')
            ->select('COUNT(up.id)')
            ->andWhere('up.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
