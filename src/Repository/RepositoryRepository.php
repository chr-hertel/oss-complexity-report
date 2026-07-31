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
