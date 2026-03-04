<?php

namespace App\Repository;

use App\Entity\Reference;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reference>
 */
class ReferenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reference::class);
    }

    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.isVisible = :isVisible')
            ->setParameter('isVisible', true)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByYearAndSlug(int $year, string $slug): ?Reference
    {
        return $this->createQueryBuilder('r')
            ->where('r.slug = :slug')
            ->andWhere('r.createdAt >= :startOfYear')
            ->andWhere('r.createdAt < :startOfNextYear')
            ->setParameter('slug', $slug)
            ->setParameter('startOfYear', new \DateTime("$year-01-01"))
            ->setParameter('startOfNextYear', new \DateTime(($year + 1) . '-01-01'))
            ->getQuery()
            ->getOneOrNullResult();
    }
}
