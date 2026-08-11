<?php

declare(strict_types=1);

namespace App\Tests\ComplexityReport;

use App\ComplexityReport\RankedRepository;
use App\ComplexityReport\ReleaseSummary;
use App\Entity\Organization;
use App\Entity\Repository;
use PHPUnit\Framework\TestCase;

/**
 * Which releases a card is about is picked by the query that groups them, so what is left to check here
 * is what the report makes of them: the two changes in percent, and the two cases that have nothing to
 * compare against.
 */
final class RankedRepositoryTest extends TestCase
{
    public function testItCarriesTheFiguresOfTheReleasesItSummarises(): void
    {
        $ranked = RankedRepository::from(self::repository(), self::summary(2.0, 2.5, 12, 1500));

        self::assertSame('symfony/console', $ranked->repository->getName());
        self::assertSame('1.0', $ranked->firstRelease);
        self::assertSame(12, $ranked->releaseCount);
        self::assertSame(2.5, $ranked->complexity);
        self::assertSame(1500, $ranked->linesOfCode);
    }

    /**
     * @dataProvider evolutions
     */
    public function testItMeasuresTheEvolutionOfTheAverageComplexity(float $first, float $latest, float $expected): void
    {
        self::assertSame($expected, RankedRepository::from(self::repository(), self::summary($first, $latest))->evolution);
    }

    /**
     * @return iterable<string, array{float, float, float}>
     */
    public static function evolutions(): iterable
    {
        yield 'simpler' => [2.0, 1.5, -25.0];
        yield 'hairier' => [2.0, 2.5, 25.0];
        yield 'unchanged' => [2.0, 2.0, 0.0];
        // a release without a single class has no average to compare against
        yield 'no complexity to compare' => [0.0, 2.0, 0.0];
    }

    /**
     * @dataProvider windows
     */
    public function testItMeasuresTheEvolutionAgainstTheBaselineOfTheWindow(?float $baseline, float $expected): void
    {
        $summary = self::summary(2.0, 2.5, 12, 1500, $baseline);

        self::assertSame($expected, RankedRepository::from(self::repository(), $summary)->recentEvolution);
    }

    /**
     * @return iterable<string, array{float|null, float}>
     */
    public static function windows(): iterable
    {
        // the release the repository stood at when the window opened is what it is compared against,
        // not its first one - 2.0 a year ago against 2.5 today
        yield 'the release it stood at back then' => [2.0, 25.0];
        // a repository not measured back then has nothing to say about the window, and one that has not
        // released within it is compared against its latest release, which is no change either way
        yield 'nothing to compare against' => [null, 0.0];
        yield 'the same release it still stands at' => [2.5, 0.0];
    }

    private static function summary(float $first, float $latest, int $count = 12, int $linesOfCode = 1500, ?float $baseline = null): ReleaseSummary
    {
        return new ReleaseSummary(
            1,
            $count,
            '1.0',
            new \DateTimeImmutable('2011-04-01'),
            $first,
            $latest,
            $linesOfCode,
            $baseline,
        );
    }

    private static function repository(): Repository
    {
        return new Repository(
            'symfony/console',
            'https://github.com/symfony/console',
            'https://github.com/symfony/console.git',
            new Organization('symfony'),
        );
    }
}
