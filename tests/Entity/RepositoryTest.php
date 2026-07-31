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
        self::assertSame(0.0, $repository->getRecentEvolution());
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
     * @dataProvider windows
     *
     * @param list<array{string, float, string}> $releases
     */
    public function testItMeasuresTheEvolutionSinceAPointInTime(array $releases, float $expected): void
    {
        $repository = self::repositoryReleasedAt($releases);

        self::assertSame($expected, $repository->getEvolutionSince(new \DateTimeImmutable('-12 months')));
    }

    /**
     * @return iterable<string, array{list<array{string, float, string}>, float}>
     */
    public static function windows(): iterable
    {
        // the release the repository stood at when the window opened is what it is compared against,
        // not its first one - 2.5 a year ago against 3.0 today, although it started out at 2.0
        yield 'the release it stood at back then' => [
            [['1.0', 2.0, '-3 years'], ['2.0', 2.5, '-18 months'], ['3.0', 3.0, '-6 months']],
            20.0,
        ];
        yield 'nothing released within the window' => [
            [['1.0', 2.0, '-3 years'], ['2.0', 4.0, '-2 years']],
            0.0,
        ];
        yield 'not measured yet when the window opened' => [
            [['1.0', 2.0, '-6 months'], ['2.0', 3.0, '-1 month']],
            0.0,
        ];
    }

    public function testTheRecentEvolutionCoversTheLastTwelveMonths(): void
    {
        $repository = self::repositoryReleasedAt([['1.0', 2.0, '-13 months'], ['2.0', 2.5, '-1 month']]);

        self::assertSame(25.0, $repository->getRecentEvolution());
    }

    /**
     * @param list<array{string, float, string}> $releases
     */
    private static function repositoryReleasedAt(array $releases): Repository
    {
        $repository = self::repositoryWith([]);

        foreach ($releases as [$name, $complexity, $created]) {
            $repository->addTag(new GitTag($name), new Analysis(100, $complexity, new \DateTimeImmutable($created)));
        }

        return $repository;
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
