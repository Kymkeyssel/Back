<?php

namespace App\Repository;

use App\Entity\Agency;
use App\Entity\Payment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Payment>
 */
class PaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    public function save(Payment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Payment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByTransactionId(string $transactionId): ?Payment
    {
        return $this->findOneBy(['transactionId' => $transactionId]);
    }

    public function findByProviderReference(string $providerReference): ?Payment
    {
        return $this->findOneBy(['providerReference' => $providerReference]);
    }

    public function findByNotchPaymentReference(string $notchPaymentReference): ?Payment
    {
        return $this->findOneBy(['notchPaymentReference' => $notchPaymentReference]);
    }

    public function findActiveByBooking(int $bookingId): ?Payment
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.booking = :bookingId')
            ->andWhere('p.status IN (:statuses)')
            ->setParameter('bookingId', $bookingId)
            ->setParameter('statuses', [
                Payment::STATUS_PENDING,
                Payment::STATUS_PROCESSING,
            ])
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByUser(int $userId): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.user = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByBooking(int $bookingId): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.booking = :bookingId')
            ->setParameter('bookingId', $bookingId)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findCompletedPayments(): array
    {
        return $this->findBy(['status' => Payment::STATUS_COMPLETED], ['completedAt' => 'DESC']);
    }

    public function findPendingPayments(): array
    {
        return $this->findBy(
            ['status' => [Payment::STATUS_PENDING, Payment::STATUS_PROCESSING]],
            ['createdAt' => 'DESC']
        );
    }

    public function findPaymentsByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.createdAt BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function sumCompletedPaymentsByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): float
    {
        $result = $this->createQueryBuilder('p')
            ->select('SUM(p.amount) as total')
            ->andWhere('p.status = :status')
            ->andWhere('p.createdAt BETWEEN :startDate AND :endDate')
            ->setParameter('status', Payment::STATUS_COMPLETED)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result['total'] ?? 0);
    }

    public function totalCompletedAmount(): float
    {
        $result = $this->createQueryBuilder('p')
            ->select('COALESCE(SUM(p.amount), 0) as total')
            ->andWhere('p.status = :status')
            ->setParameter('status', Payment::STATUS_COMPLETED)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) $result;
    }

    public function findByAgency(int $agencyId): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.booking', 'b')
            ->join('b.trip', 't')
            ->andWhere('t.agency = :agencyId')
            ->setParameter('agencyId', $agencyId)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findCompletedPaymentsByAgencyAndDateRange(Agency $agency, \DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.booking', 'b')
            ->join('b.trip', 't')
            ->andWhere('t.agency = :agency')
            ->andWhere('p.status = :status')
            ->andWhere('p.createdAt BETWEEN :startDate AND :endDate')
            ->setParameter('agency', $agency)
            ->setParameter('status', Payment::STATUS_COMPLETED)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
