<?php

declare(strict_types=1);

namespace App\Repository;

use App\ComplexityReport\Vendor;
use App\Entity\Organization;
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
     * The accounts that group something: they need more than one repository to chart, and every one of
     * those needs releases - an owner of a single repository is that repository, which the rankings
     * already link to, and an owner without measured releases links to an empty report.
     *
     * They are listed by how much of the report they account for, because that is the number their pill
     * carries; stars only break the tie between two accounts of the same size.
     *
     * Nothing is loaded for this - the account is a login and the pill is a list of names, so the whole
     * row is one grouped query. `string_agg` puts the names of an account into its row in the order the
     * chart link needs them, which is also the order the query string is written in: a repository is
     * `owner/name` on github.com and neither half can carry a comma, so the separator holds.
     *
     * @return list<Vendor>
     */
    public function findVendors(): array
    {
        $sql = <<<'SQL'
            SELECT o.login                                            AS login,
                   string_agg(r.name, ',' ORDER BY r.stars DESC, r.name) AS repositories
            FROM repository r
                INNER JOIN organization o ON o.id = r.organization_id
            WHERE EXISTS (SELECT 1 FROM tag t WHERE t.repository_id = r.id)
            GROUP BY o.id, o.login
            HAVING count(*) >= :minimum
            ORDER BY count(*) DESC, sum(r.stars) DESC, o.login ASC
            SQL;

        /** @var list<array{login: string, repositories: string}> $rows */
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            $sql,
            ['minimum' => Vendor::MINIMUM],
        );

        return array_map(
            static fn (array $row) => new Vendor($row['login'], explode(',', $row['repositories'])),
            $rows
        );
    }
}
