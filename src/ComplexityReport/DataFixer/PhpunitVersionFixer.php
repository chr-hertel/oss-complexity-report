<?php

declare(strict_types=1);

namespace App\ComplexityReport\DataFixer;

use App\Entity\Library;
use App\Entity\Tag;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use PhpParser\Node\Expr;

/**
 * Removing PHPUnit tags that don't have correct dates.
 * -> All versions <=2.3.0.
 */
final class PhpunitVersionFixer implements FixerInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function fixData(): void
    {
        $this->entityManager->createQueryBuilder()
            ->delete()
            ->from(Tag::class, 't')
            ->join('t.library', 'l', Join::WITH, 'l.name = "phpunit/phpunit"')
            ->where('t.created = :date')
            ->setParameter('date', new DateTimeImmutable('2006-07-04 12:01:50'), Types::DATETIME_IMMUTABLE)
            ->getQuery()
            ->execute();
    }
}
