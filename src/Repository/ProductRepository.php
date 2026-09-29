<?php

namespace App\Repository;

use App\Catalog\ProductFilters;
use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /**
     * Shop query for the given filters (not executed: the controller paginates it).
     */
    public function createCatalogQuery(ProductFilters $filters): QueryBuilder
    {
        $qb = $this->createQueryBuilder('p');

        if ($filters->category !== null) {
            $qb->andWhere('p.category = :category')->setParameter('category', $filters->category);
        }

        if ($filters->query !== '') {
            // Bound parameter (no SQL injection); % and _ typed by the visitor are matched literally.
            $qb->andWhere('LOWER(p.name) LIKE :query')
                ->setParameter('query', '%' . addcslashes(mb_strtolower($filters->query), '%_\\') . '%');
        }

        if ($filters->minPriceCents !== null) {
            $qb->andWhere('p.price >= :min')->setParameter('min', $filters->minPriceCents);
        }
        if ($filters->maxPriceCents !== null) {
            $qb->andWhere('p.price <= :max')->setParameter('max', $filters->maxPriceCents);
        }

        if ($filters->inStockOnly) {
            $qb->andWhere('p.stock > 0');
        }

        match ($filters->sort) {
            'prix-asc' => $qb->orderBy('p.price', SortDirection::Ascending),
            'prix-desc' => $qb->orderBy('p.price', SortDirection::Descending),
            default => $qb->orderBy('p.createdAt', SortDirection::Descending),
        };
        // Stable order between pages when several products share a price or a date.
        $qb->addOrderBy('p.id', SortDirection::Descending);

        return $qb;
    }

    /**
     * Number of products per category, in one query.
     *
     * @return array<string, int> category => count, sorted by category
     */
    public function countByCategory(): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('p.category AS category, COUNT(p.id) AS total')
            ->groupBy('p.category')
            ->orderBy('p.category', SortDirection::Ascending)
            ->getQuery()
            ->getArrayResult();

        return array_column(array_map(fn (array $row) => [$row['category'], (int) $row['total']], $rows), 1, 0);
    }

    /**
     * Product to put forward on the home page: the newest one on sale and in stock,
     * or else the newest one in stock.
     */
    public function findFeatured(): ?Product
    {
        return $this->createQueryBuilder('p')
            ->where('p.stock > 0')
            ->orderBy('p.isSale', SortDirection::Descending)
            ->addOrderBy('p.createdAt', SortDirection::Descending)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Other products of the same category, newest first.
     *
     * @return list<Product>
     */
    public function findRelated(Product $product, int $limit = 4): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.category = :category')
            ->andWhere('p.id != :id')
            ->setParameter('category', $product->getCategory())
            ->setParameter('id', $product->getId())
            ->orderBy('p.createdAt', SortDirection::Descending)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    //    /**
    //     * @return Product[] Returns an array of Product objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('p.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Product
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
