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
     * The most popular repositories that actually have data to show.
     *
     * @return list<Repository>
     */
    public function findMostStarred(int $limit): array
    {
        /** @var list<Repository> $repositories */
        $repositories = $this->withData()
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $repositories;
    }

    /**
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
     * Submitted repositories that are waiting for their first analysis.
     *
     * @return list<Repository>
     */
    public function findPending(): array
    {
        /** @var list<Repository> $repositories */
        $repositories = $this->createQueryBuilder('r')
            ->where('r.analysed IS NULL')
            ->orderBy('r.submitted', 'ASC')
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
     * @return list<int>
     */
    public function findPendingIds(): array
    {
        return $this->idsOf(
            $this->createQueryBuilder('r')->where('r.analysed IS NULL')->orderBy('r.submitted', 'ASC')
        );
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
