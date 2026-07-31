<?php

declare(strict_types=1);

namespace App\Repository;

use App\ComplexityReport\Trend\ReleasePoint;
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

    /**
     * Every measured release reduced to the three values a trend is computed from - the whole report as
     * one query, without hydrating a single entity.
     *
     * @return list<ReleasePoint>
     */
    public function findReleasePoints(): array
    {
        /** @var list<array{repository: int|string, created: \DateTimeImmutable, complexity: float|string}> $rows */
        $rows = $this->createQueryBuilder('t')
            ->select('IDENTITY(t.repository) AS repository, t.created AS created, t.averageComplexity AS complexity')
            ->orderBy('t.created', 'ASC')
            ->addOrderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(
            static fn (array $row) => new ReleasePoint((int) $row['repository'], $row['created'], (float) $row['complexity']),
            $rows
        );
    }

    public function getLinesOfCodeSum(): int
    {
        $query = $this->createQueryBuilder('t')
            ->select('SUM(t.linesOfCode)')
            ->getQuery();

        return (int) $query->getSingleScalarResult();
    }
}
