<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\ComplexityReport\Analysis;
use App\ComplexityReport\GitTag;
use App\Entity\Organization;
use App\Entity\Repository;
use PHPUnit\Framework\TestCase;

final class RepositoryTest extends TestCase
{
    public function testItReportsTheFiguresOfTheLatestRelease(): void
    {
        $repository = self::repositoryWith(['2.0' => [1000, 2.5], '3.0' => [1500, 2.0]]);

        self::assertSame(2, $repository->getReleaseCount());
        self::assertSame('2.0', $repository->getFirstTag()?->getName());
        self::assertSame('3.0', $repository->getLatestTag()?->getName());
        self::assertSame(1500, $repository->getLinesOfCode());
        self::assertSame(2.0, $repository->getComplexity());
    }

    public function testItHasNoFiguresWithoutAnalysedReleases(): void
    {
        $repository = self::repositoryWith([]);

        self::assertNull($repository->getFirstTag());
        self::assertNull($repository->getLatestTag());
        self::assertSame(0, $repository->getReleaseCount());
        self::assertSame(0, $repository->getLinesOfCode());
        self::assertSame(0.0, $repository->getComplexity());
        self::assertSame(0.0, $repository->getEvolution());
    }

    /**
     * @dataProvider evolutions
     *
     * @param array<string, array{int, float}> $releases
     */
    public function testItMeasuresTheEvolutionOfTheAverageComplexity(array $releases, float $expected): void
    {
        self::assertSame($expected, self::repositoryWith($releases)->getEvolution());
    }

    /**
     * @return iterable<string, array{array<string, array{int, float}>, float}>
     */
    public static function evolutions(): iterable
    {
        yield 'simpler' => [['1.0' => [100, 2.0], '2.0' => [100, 1.5]], -25.0];
        yield 'hairier' => [['1.0' => [100, 2.0], '2.0' => [100, 2.5]], 25.0];
        yield 'unchanged' => [['1.0' => [100, 2.0], '2.0' => [100, 2.0]], 0.0];
        yield 'a single release did not evolve yet' => [['1.0' => [100, 2.0]], 0.0];
        // a release without a single class has no average to compare against
        yield 'no complexity to compare' => [['1.0' => [100, 0.0], '2.0' => [100, 2.0]], 0.0];
    }

    /**
     * @param array<string, array{int, float}> $releases
     */
    private static function repositoryWith(array $releases): Repository
    {
        $organization = new Organization('symfony');
        $repository = new Repository(
            'symfony/console',
            'https://github.com/symfony/console',
            'https://github.com/symfony/console.git',
            $organization,
        );

        $created = new \DateTimeImmutable('2020-01-01');

        foreach ($releases as $name => [$linesOfCode, $complexity]) {
            $repository->addTag(new GitTag($name), new Analysis($linesOfCode, $complexity, $created));
            $created = $created->modify('+1 year');
        }

        return $repository;
    }
}
