<?php

namespace App\Repository;

use App\Entity\Ticket;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Ticket>
 */
class TicketRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ticket::class);
    }

    public function save(Ticket $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Ticket $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByQrCode(string $qrCode): ?Ticket
    {
        return $this->findOneBy(['qrCode' => $qrCode]);
    }

    public function findByUser(int $userId): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.user = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByBooking(int $bookingId): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.booking = :bookingId')
            ->setParameter('bookingId', $bookingId)
            ->orderBy('t.seatNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findActiveTickets(): array
    {
        return $this->findBy(['status' => 'active'], ['createdAt' => 'DESC']);
    }

    public function findUsedTickets(): array
    {
        return $this->findBy(['status' => 'used'], ['scannedAt' => 'DESC']);
    }

    public function findTicketsByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.createdAt BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
