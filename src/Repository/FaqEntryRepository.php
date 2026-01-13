<?php

namespace App\Repository;

use App\Entity\FaqEntry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FaqEntry>
 */
class FaqEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FaqEntry::class);
    }

    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.isVisible = :isVisible')
            ->setParameter('isVisible', true)
            ->orderBy('f.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
