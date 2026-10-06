<?php

namespace App\Repository;

use App\Entity\Product;
use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    public function save(Product $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Product $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findActiveProducts(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findFeaturedProducts(int $limit = 8): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isActive = :active')
            ->andWhere('p.isFeatured = :featured')
            ->setParameter('active', true)
            ->setParameter('featured', true)
            ->orderBy('p.createdAt', 'DESC')
            ->addOrderBy('p.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findLatestProducts(int $limit = 12): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('p.createdAt', 'DESC')
            ->addOrderBy('p.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findByCategory(Category $category): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.category = :category')
            ->andWhere('p.isActive = :active')
            ->setParameter('category', $category)
            ->setParameter('active', true)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public const SORTS = [
        'nouveautes' => 'Nouveautés',
        'prix-croissant' => 'Prix croissant',
        'prix-decroissant' => 'Prix décroissant',
        'nom' => 'Nom, de A à Z',
    ];

    /**
     * Liste du catalogue, filtrée et triée par la base.
     *
     * @return array{items: Product[], total: int, pages: int, page: int}
     */
    public function findForListing(
        ?Category $category = null,
        string $query = '',
        string $sort = 'nouveautes',
        bool $inStockOnly = false,
        bool $discountedOnly = false,
        int $page = 1,
        int $perPage = 24,
    ): array {
        $qb = $this->createQueryBuilder('p')
            ->innerJoin('p.category', 'c')->addSelect('c')
            ->andWhere('p.isActive = :active')
            ->setParameter('active', true);

        if ($category) {
            $qb->andWhere('p.category = :category')->setParameter('category', $category);
        }

        $query = trim(mb_substr($query, 0, 80));
        if ('' !== $query) {
            $qb->andWhere('LOWER(p.name) LIKE :query OR LOWER(p.description) LIKE :query OR LOWER(c.name) LIKE :query')
                ->setParameter('query', '%' . addcslashes(mb_strtolower($query), '%_\\') . '%');
        }

        if ($inStockOnly) {
            $qb->andWhere('p.stock > 0');
        }

        if ($discountedOnly) {
            $qb->andWhere('p.oldPrice IS NOT NULL AND p.oldPrice > p.price');
        }

        $total = (int) (clone $qb)->select('COUNT(p.id)')->getQuery()->getSingleScalarResult();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);

        match ($sort) {
            'prix-croissant' => $qb->orderBy('p.price', 'ASC'),
            'prix-decroissant' => $qb->orderBy('p.price', 'DESC'),
            'nom' => $qb->orderBy('p.name', 'ASC'),
            default => $qb->orderBy('p.createdAt', 'DESC'),
        };
        $qb->addOrderBy('p.id', 'DESC');

        $items = $qb->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage)->getQuery()->getResult();

        return ['items' => $items, 'total' => $total, 'pages' => $pages, 'page' => $page];
    }

    /**
     * @return Product[]
     */
    public function findRelated(Product $product, int $limit = 4): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.category = :category')
            ->andWhere('p.isActive = :active')
            ->andWhere('p.id <> :id')
            ->setParameter('category', $product->getCategory())
            ->setParameter('active', true)
            ->setParameter('id', $product->getId())
            ->orderBy('p.stock', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Product[]
     */
    public function findLowStock(int $threshold = 5, int $limit = 8): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isActive = :active')
            ->andWhere('p.stock <= :threshold')
            ->setParameter('active', true)
            ->setParameter('threshold', $threshold)
            ->orderBy('p.stock', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countActive(): int
    {
        return $this->count(['isActive' => true]);
    }

    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        $qb = $this->createQueryBuilder('p')->select('COUNT(p.id)')->andWhere('p.slug = :slug')->setParameter('slug', $slug);
        if (null !== $exceptId) {
            $qb->andWhere('p.id <> :id')->setParameter('id', $exceptId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * @return Product[]
     */
    public function searchProducts(string $query, int $limit = 48): array
    {
        return $this->findForListing(query: $query, perPage: $limit)['items'];
    }
}
