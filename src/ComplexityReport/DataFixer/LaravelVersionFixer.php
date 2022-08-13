<?php

declare(strict_types=1);

namespace App\ComplexityReport\DataFixer;

use App\Entity\Tag;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;

/**
 * Removing several Laravel minor releases for versions 6 or 7 that mess up the chart.
 * -> 6.19, 6.20, 7.29, 7.30.
 */
final class LaravelVersionFixer implements FixerInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function fixData(): void
    {
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder
            ->delete()
            ->from(Tag::class, 't')
            ->join('t.library', 'l', Join::WITH, 'l.name = "laravel/framework"')
            ->where(
                $queryBuilder->expr()->in('t.name', ['v6.19.0', 'v6.20.0', 'v7.29.0', 'v7.30.0']),
            )
            ->getQuery()
            ->execute();
    }
}
