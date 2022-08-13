<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Tag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tag>
 */
final class TagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tag::class);
    }

    public function getLinesOfCodeSum(): int
    {
        $query = $this->createQueryBuilder('t')
            ->select('SUM(t.linesOfCode)')
            ->getQuery();

        return (int) $query->getSingleScalarResult();
    }
}
