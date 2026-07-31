<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Project;
use App\Entity\Tag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Project>
 *
 * @method Project|null findOneByName(string $name)
 * @method Project|null findOneByVendor(string $vendor)
 */
final class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    /**
     * Vendors ordered by the popularity of everything they got submitted.
     *
     * @return list<Project>
     */
    public function findAll(): array
    {
        /** @var list<Project> $projects */
        $projects = $this->createQueryBuilder('p')
            ->select('p, SUM(r.stars) AS HIDDEN stars')
            ->leftJoin('p.repositories', 'r')
            ->groupBy('p.id')
            ->orderBy('stars', 'DESC')
            ->addOrderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $projects;
    }

    /**
     * Only the vendors that have something to chart - everything else links to an empty report.
     *
     * @return list<Project>
     */
    public function findWithData(): array
    {
        $queryBuilder = $this->createQueryBuilder('p');

        /** @var list<Project> $projects */
        $projects = $queryBuilder
            ->select('p, SUM(r.stars) AS HIDDEN stars')
            ->innerJoin('p.repositories', 'r')
            ->where($queryBuilder->expr()->exists(
                sprintf('SELECT t.id FROM %s t WHERE t.repository = r', Tag::class)
            ))
            ->groupBy('p.id')
            ->orderBy('stars', 'DESC')
            ->addOrderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $projects;
    }
}
