<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Repository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Repository>
 *
 * @method Repository|null findOneByName(string $name)
 */
final class RepositoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Repository::class);
    }

    /**
     * Everything that has data to show, most starred first - what the chart can be built from.
     *
     * @return list<Repository>
     */
    public function findAnalysed(): array
    {
        /** @var list<Repository> $repositories */
        $repositories = $this->withData()->getQuery()->getResult();

        return $repositories;
    }

    /**
     * Everything that has data, with its releases already loaded.
     *
     * The rankings on the start page read the first and the latest release of every repository, which is
     * one query for all of them instead of one per repository.
     *
     * @return list<Repository>
     */
    public function findAnalysedWithTags(): array
    {
        /** @var list<Repository> $repositories */
        $repositories = $this->createQueryBuilder('r')
            ->addSelect('t')
            ->innerJoin('r.tags', 't')
            ->orderBy('r.stars', 'DESC')
            ->addOrderBy('r.name', 'ASC')
            // spelled out rather than left to the association's OrderBy, so the first and the last
            // element of the collection are the first and the latest release in every Doctrine version
            ->addOrderBy('t.created', 'ASC')
            ->getQuery()
            ->getResult();

        return $repositories;
    }

    /**
     * The repositories behind the given `owner/repository` slugs, in the order they were asked for -
     * unknown ones are dropped, so a link to a repository that is gone still opens a chart. github.com
     * does not care about the case of a slug, and neither does a link somebody typed by hand.
     *
     * @param list<string> $slugs
     *
     * @return list<Repository>
     */
    public function findBySlugs(array $slugs): array
    {
        if ([] === $slugs) {
            return [];
        }

        /** @var list<Repository> $repositories */
        $repositories = $this->createQueryBuilder('r')
            ->where('LOWER(r.name) IN (:slugs)')
            ->setParameter('slugs', array_map(mb_strtolower(...), $slugs))
            ->getQuery()
            ->getResult();

        $found = [];

        foreach ($repositories as $repository) {
            $found[mb_strtolower($repository->getName())] = $repository;
        }

        return array_values(array_filter(array_map(
            static fn (string $slug) => $found[mb_strtolower($slug)] ?? null,
            $slugs,
        )));
    }

    public function findBySlug(string $slug): ?Repository
    {
        return $this->findBySlugs([$slug])[0] ?? null;
    }

    /**
     * Repositories whose name contains the query, most popular first - what the search box offers while
     * someone types.
     *
     * @return list<Repository>
     */
    public function findByNameLike(string $query, int $limit): array
    {
        /** @var list<Repository> $repositories */
        $repositories = $this->createQueryBuilder('r')
            ->where('LOWER(r.name) LIKE :query')
            ->setParameter('query', '%'.self::escapeForLike(mb_strtolower($query)).'%')
            ->orderBy('r.stars', 'DESC')
            ->addOrderBy('r.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $repositories;
    }

    /**
     * Repository names are full of underscores, which LIKE would read as "any character".
     */
    private static function escapeForLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * How much work is waiting - counted rather than loaded, the submitter only needs the number.
     */
    public function countPending(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.analysed IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * What was submitted last, newest first - measured or not, since a repository is part of the report
     * from the moment somebody added it, and the rankings only carry what has numbers.
     *
     * @return list<Repository>
     */
    public function findLatest(int $limit): array
    {
        /** @var list<Repository> $repositories */
        $repositories = $this->createQueryBuilder('r')
            ->orderBy('r.submitted', 'DESC')
            // a batch submitted in one go shares its timestamp, so the order needs a second opinion
            ->addOrderBy('r.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $repositories;
    }

    /**
     * Ids only - queueing work does not need the entities behind them.
     *
     * @return list<int>
     */
    public function findAllIds(): array
    {
        return $this->idsOf($this->createQueryBuilder('r')->orderBy('r.stars', 'DESC'));
    }

    /**
     * Repositories carrying releases that were measured before the report kept the full phploc output -
     * what the hourly backfill works off, a few at a time.
     *
     * @return list<int>
     */
    public function findIncompleteIds(int $limit): array
    {
        return $this->idsOf($this->incomplete()->setMaxResults($limit));
    }

    /**
     * The same repositories with the number of releases each of them is still missing - what
     * `app:metrics:status` names, in the order the next runs would take them.
     *
     * @return list<array{name: string, missing: int}>
     */
    public function findIncomplete(int $limit): array
    {
        /** @var list<array{name: string, missing: int|string}> $rows */
        $rows = $this->incomplete()
            ->select('r.name AS name, COUNT(t.id) AS missing')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_map(
            static fn (array $row) => ['name' => $row['name'], 'missing' => (int) $row['missing']],
            $rows
        );
    }

    /**
     * Repositories that carry at least one measured release - what an incomplete one is counted against.
     *
     * Not every submitted repository can be incomplete: one that was never measured has no release to be
     * missing an output, so counting those into the total would report them as done.
     */
    public function countMeasured(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(DISTINCT r.id)')
            ->innerJoin('r.tags', 't')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * How many repositories the backfill has left, however many releases each of them is missing - one
     * of them costs a clone either way, which is what the hourly run is rationed in.
     */
    public function countIncomplete(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(DISTINCT r.id)')
            ->innerJoin('r.tags', 't')
            ->where('t.metrics IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Repositories carrying releases without their phploc output, most starred first - the order the
     * report picks everything by, so the repositories being read get theirs first.
     */
    private function incomplete(): QueryBuilder
    {
        return $this->createQueryBuilder('r')
            ->innerJoin('r.tags', 't')
            ->where('t.metrics IS NULL')
            ->groupBy('r.id')
            ->orderBy('r.stars', 'DESC')
            ->addOrderBy('r.name', 'ASC');
    }

    /**
     * @return list<int>
     */
    private function idsOf(QueryBuilder $queryBuilder): array
    {
        /** @var list<array{id: int}> $rows */
        $rows = $queryBuilder->select('r.id')->getQuery()->getArrayResult();

        return array_column($rows, 'id');
    }

    private function withData(): QueryBuilder
    {
        return $this->createQueryBuilder('r')
            ->innerJoin('r.tags', 't')
            ->groupBy('r.id')
            ->orderBy('r.stars', 'DESC')
            ->addOrderBy('r.name', 'ASC');
    }
}
