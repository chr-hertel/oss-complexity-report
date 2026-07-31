<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Organization;
use App\Entity\Tag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Organization>
 *
 * @method Organization|null findOneByLogin(string $login)
 */
final class OrganizationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Organization::class);
    }

    /**
     * Organizations ordered by the popularity of everything they got submitted.
     *
     * @return list<Organization>
     */
    public function findAll(): array
    {
        /** @var list<Organization> $organizations */
        $organizations = $this->createQueryBuilder('o')
            ->select('o, SUM(r.stars) AS HIDDEN stars')
            ->leftJoin('o.repositories', 'r')
            ->groupBy('o.id')
            ->orderBy('stars', 'DESC')
            ->addOrderBy('o.login', 'ASC')
            ->getQuery()
            ->getResult();

        return $organizations;
    }

    /**
     * Only the organizations that have something to chart - everything else links to an empty report.
     *
     * @return list<Organization>
     */
    public function findWithData(): array
    {
        $queryBuilder = $this->createQueryBuilder('o');

        /** @var list<Organization> $organizations */
        $organizations = $queryBuilder
            ->select('o, SUM(r.stars) AS HIDDEN stars')
            ->innerJoin('o.repositories', 'r')
            ->where($queryBuilder->expr()->exists(
                sprintf('SELECT t.id FROM %s t WHERE t.repository = r', Tag::class)
            ))
            ->groupBy('o.id')
            ->orderBy('stars', 'DESC')
            ->addOrderBy('o.login', 'ASC')
            ->getQuery()
            ->getResult();

        return $organizations;
    }
}
