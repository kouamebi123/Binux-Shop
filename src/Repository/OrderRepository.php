<?php

namespace App\Repository;

use App\Entity\Order;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    public function save(Order $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Order $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.user = :user')
            ->setParameter('user', $user)
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findRecentOrders(int $limit = 10): array
    {
        return $this->createQueryBuilder('o')
            ->orderBy('o.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Commandes en attente, jamais payées, créées avant la date donnée.
     *
     * @return Order[]
     */
    public function findUnpaidBefore(\DateTimeImmutable $limit): array
    {
        return $this->createQueryBuilder('o')
            ->leftJoin('o.payment', 'p')
            ->andWhere('o.status = :pending')
            ->andWhere('o.createdAt < :limit')
            ->andWhere('p.id IS NULL OR p.status <> :paid')
            ->setParameter('pending', Order::STATUS_PENDING)
            ->setParameter('paid', \App\Entity\Payment::STATUS_SUCCEEDED)
            ->setParameter('limit', $limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Order[]
     */
    public function findForAdmin(?string $status = null, int $limit = 200): array
    {
        $qb = $this->createQueryBuilder('o')
            ->leftJoin('o.payment', 'p')->addSelect('p')
            ->leftJoin('o.user', 'u')->addSelect('u')
            ->orderBy('o.createdAt', 'DESC')
            ->setMaxResults($limit);

        if (null !== $status) {
            $qb->andWhere('o.status = :status')->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Chiffres du tableau de bord, calculés par la base : encaissé, commandes payées, à traiter.
     *
     * @return array{revenue_cents: int, paid_orders: int, to_process: int, awaiting_payment: int, total: int}
     */
    public function dashboardFigures(): array
    {
        $connection = $this->getEntityManager()->getConnection();

        $paid = $connection->fetchAssociative(
            'SELECT COALESCE(SUM(ROUND(o.total * 100)), 0) AS revenue, COUNT(*) AS paid_orders
             FROM "order" o INNER JOIN payment p ON p.order_ref_id = o.id
             WHERE p.status = :paid AND o.status <> :cancelled',
            ['paid' => \App\Entity\Payment::STATUS_SUCCEEDED, 'cancelled' => Order::STATUS_CANCELLED]
        );

        $byStatus = $connection->fetchAllKeyValue('SELECT status, COUNT(*) FROM "order" GROUP BY status');

        return [
            'revenue_cents' => (int) $paid['revenue'],
            'paid_orders' => (int) $paid['paid_orders'],
            'to_process' => (int) ($byStatus[Order::STATUS_PROCESSING] ?? 0),
            'awaiting_payment' => (int) ($byStatus[Order::STATUS_PENDING] ?? 0),
            'total' => (int) array_sum($byStatus),
        ];
    }

    /**
     * Encaissements par jour sur la période, jours sans vente compris.
     *
     * @return array<string, int> date (Y-m-d) => centimes
     */
    public function revenueByDay(int $days = 14, ?\DateTimeImmutable $today = null): array
    {
        $today = ($today ?? new \DateTimeImmutable())->setTime(0, 0);
        $start = $today->modify(sprintf('-%d days', $days - 1));

        $rows = $this->getEntityManager()->getConnection()->fetchAllKeyValue(
            'SELECT CAST(p.paid_at AS DATE) AS day, SUM(ROUND(o.total * 100)) AS cents
             FROM "order" o INNER JOIN payment p ON p.order_ref_id = o.id
             WHERE p.status = :paid AND o.status <> :cancelled AND p.paid_at >= :start
             GROUP BY CAST(p.paid_at AS DATE)',
            [
                'paid' => \App\Entity\Payment::STATUS_SUCCEEDED,
                'cancelled' => Order::STATUS_CANCELLED,
                'start' => $start->format('Y-m-d H:i:s'),
            ]
        );

        $series = [];
        for ($i = 0; $i < $days; ++$i) {
            $day = $start->modify(sprintf('+%d days', $i))->format('Y-m-d');
            $series[$day] = (int) ($rows[$day] ?? 0);
        }

        return $series;
    }
}
