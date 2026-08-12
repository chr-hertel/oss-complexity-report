<?php

declare(strict_types=1);

namespace App\Repository;

use App\ComplexityReport\ReleaseSummary;
use App\ComplexityReport\Trend\ReleasePoint;
use App\Entity\Tag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
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
     * The releases of every repository, grouped into the figures a ranking card is drawn from - one row
     * per repository instead of one per release.
     *
     * A card is about three of the releases a repository ever had: its first, its latest, and the one it
     * stood at when `$since` opened the window. Reading twenty thousand rows to pick three of each was
     * what made the start page a question of how much memory it may have, so the picking is left to the
     * engine: `array_agg` orders the releases of a group and the subscript takes the end being asked
     * for, the `FILTER` doing it again over what the window contains. This is native SQL because DQL
     * knows neither, which is the price - the table and its columns are spelled out here.
     *
     * @return list<ReleaseSummary>
     */
    public function findReleaseSummaries(\DateTimeImmutable $since): array
    {
        $sql = <<<'SQL'
            SELECT t.repository_id                                                              AS repository,
                   count(*)                                                                     AS releases,
                   (array_agg(t.name ORDER BY t.created, t.name))[1]                            AS first_name,
                   (array_agg(t.created ORDER BY t.created, t.name))[1]                         AS first_created,
                   (array_agg(t.average_complexity ORDER BY t.created, t.name))[1]              AS first_complexity,
                   (array_agg(t.average_complexity ORDER BY t.created DESC, t.name DESC))[1]    AS complexity,
                   (array_agg(t.lines_of_code ORDER BY t.created DESC, t.name DESC))[1]         AS lines_of_code,
                   (array_agg(t.average_complexity ORDER BY t.created DESC, t.name DESC)
                       FILTER (WHERE t.created <= :since))[1]                                   AS baseline
            FROM tag t
            GROUP BY t.repository_id
            SQL;

        /** @var list<array{repository: int|string, releases: int|string, first_name: string, first_created: string, first_complexity: float|string, complexity: float|string, lines_of_code: int|string, baseline: float|string|null}> $rows */
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            $sql,
            ['since' => $since],
            ['since' => Types::DATETIME_IMMUTABLE],
        );

        return array_map(
            static fn (array $row) => new ReleaseSummary(
                (int) $row['repository'],
                (int) $row['releases'],
                $row['first_name'],
                new \DateTimeImmutable($row['first_created']),
                (float) $row['first_complexity'],
                (float) $row['complexity'],
                (int) $row['lines_of_code'],
                null === $row['baseline'] ? null : (float) $row['baseline'],
            ),
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

    public function getLinesOfCodeSum(): int
    {
        $query = $this->createQueryBuilder('t')
            ->select('SUM(t.linesOfCode)')
            ->getQuery();

        return (int) $query->getSingleScalarResult();
    }
}
