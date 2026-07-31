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
     * @return list<Repository>
     */
    public function findAllByStars(): array
    {
        return $this->findBy([], ['stars' => 'desc', 'name' => 'asc']);
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
