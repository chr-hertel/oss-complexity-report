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

    /**
     * The releases that were measured last.
     *
     * Ordered by id, because that is the only record of when a release entered the report: a `Tag` is
     * written the moment its release was analysed, while the date it carries is the day it was tagged on
     * github.com. So this is the newest end of the dataset, not the newest end of PHP.
     *
     * @return list<Tag>
     */
    public function findLatest(int $limit): array
    {
        /** @var list<Tag> $tags */
        $tags = $this->createQueryBuilder('t')
            // both are shown on every chip, and neither is worth a query of its own
            ->addSelect('r', 'o')
            ->innerJoin('t.repository', 'r')
            ->innerJoin('r.organization', 'o')
            ->orderBy('t.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $tags;
    }

    /**
     * Releases that were measured before the report kept the full phploc output - what the backfill has
     * left to do, counted in the unit the report is read in rather than in repositories.
     */
    public function countMissingMetrics(): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.metrics IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getLinesOfCodeSum(): int
    {
        $query = $this->createQueryBuilder('t')
            ->select('SUM(t.linesOfCode)')
            ->getQuery();

        return (int) $query->getSingleScalarResult();
    }
}
