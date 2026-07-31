<?php

declare(strict_types=1);

namespace App\Tests\ComplexityReport\Trend;

use App\ComplexityReport\Trend\ReleasePoint;
use App\ComplexityReport\Trend\Trend;
use App\ComplexityReport\Trend\TrendCalculator;
use App\ComplexityReport\Trend\TrendWindow;
use PHPUnit\Framework\TestCase;

final class TrendCalculatorTest extends TestCase
{
    private const string NOW = '2026-07-31 12:00:00';

    /**
     * @dataProvider windows
     */
    public function testItRollsTheReportUpPerWindow(
        TrendWindow $window,
        float $from,
        float $to,
        float $change,
        int $repositoryCount,
    ): void {
        $trend = $this->calculate($window, self::report());

        self::assertTrue($trend->hasData());
        self::assertSame($window, $trend->window);
        self::assertSame($from, $trend->from);
        self::assertSame($to, $trend->to);
        self::assertSame($change, $trend->change);
        self::assertSame($repositoryCount, $trend->repositoryCount);
    }

    /**
     * The figures of {@see self::report()}, worked out by hand:
     *
     * - year to date compares 7.0 / 20.0 against 6.0 / 25.0, so 13.5 against 15.5
     * - twelve months reaches back to the 8.0 of `1`, so 14.0 against 15.5
     * - five years reaches back to its 10.0, so 15.0 against 15.5
     * - all time adds `3` at its own first release, so 20.0 against 21.33
     *
     * @return iterable<string, array{TrendWindow, float, float, float, int}>
     */
    public static function windows(): iterable
    {
        yield 'year to date' => [TrendWindow::YearToDate, 13.5, 15.5, 14.8, 2];
        yield 'twelve months' => [TrendWindow::TwelveMonths, 14.0, 15.5, 10.7, 2];
        yield 'five years' => [TrendWindow::FiveYears, 15.0, 15.5, 3.3, 2];
        yield 'all time' => [TrendWindow::AllTime, 20.0, 21.33, 6.7, 3];
    }

    public function testItCountsALibraryOnlyOnceNoMatterHowManyReleasesItHas(): void
    {
        // `1` brings four releases to the window and `2` a single one - both weigh the same
        self::assertSame(2, $this->calculate(TrendWindow::YearToDate, self::report())->repositoryCount);
    }

    public function testItLeavesOutLibrariesThatWereNotMeasuredWhenTheWindowOpened(): void
    {
        $withoutNewcomer = array_values(array_filter(
            self::report(),
            static fn (ReleasePoint $point) => 3 !== $point->repository
        ));

        // `3` was first measured in April 2026, so it moves all time - and nothing before it
        self::assertEquals(
            $this->calculate(TrendWindow::FiveYears, $withoutNewcomer),
            $this->calculate(TrendWindow::FiveYears, self::report())
        );
        self::assertNotEquals(
            $this->calculate(TrendWindow::AllTime, $withoutNewcomer),
            $this->calculate(TrendWindow::AllTime, self::report())
        );
    }

    public function testItCarriesTheLastReleaseOfALibraryThatStoppedReleasingForward(): void
    {
        $abandoned = [
            new ReleasePoint(1, new \DateTimeImmutable('2015-01-01'), 12.0),
            new ReleasePoint(1, new \DateTimeImmutable('2016-01-01'), 14.0),
        ];

        $trend = $this->calculate(TrendWindow::TwelveMonths, $abandoned);

        // it was measured before the window opened and did not move within it
        self::assertSame(1, $trend->repositoryCount);
        self::assertSame(14.0, $trend->from);
        self::assertSame(14.0, $trend->to);
        self::assertSame(0.0, $trend->change);
    }

    public function testAllTimeComparesEveryLibraryAgainstItsOwnFirstRelease(): void
    {
        $trend = $this->calculate(TrendWindow::AllTime, self::report());

        self::assertNull($trend->since);
        // the first releases are 10.0, 20.0 and 30.0
        self::assertSame(20.0, $trend->from);
    }

    public function testEveryOtherWindowKnowsTheDateItOpened(): void
    {
        $trend = $this->calculate(TrendWindow::YearToDate, self::report());

        self::assertSame('2026-01-01', $trend->since?->format('Y-m-d'));
    }

    public function testItIgnoresReleasesDatedAfterTheGivenTime(): void
    {
        $points = array_merge(self::report(), [
            new ReleasePoint(1, new \DateTimeImmutable('2030-01-01'), 99.0),
        ]);

        self::assertEquals(
            $this->calculate(TrendWindow::AllTime, self::report()),
            $this->calculate(TrendWindow::AllTime, $points)
        );
    }

    public function testItDoesNotCareInWhichOrderTheReleasesArrive(): void
    {
        self::assertEquals(
            $this->calculate(TrendWindow::AllTime, self::report()),
            $this->calculate(TrendWindow::AllTime, array_reverse(self::report()))
        );
    }

    public function testAWindowNothingReachesBackToHasNoData(): void
    {
        $newcomerOnly = [new ReleasePoint(3, new \DateTimeImmutable('2026-04-01'), 30.0)];

        $trend = $this->calculate(TrendWindow::YearToDate, $newcomerOnly);

        self::assertFalse($trend->hasData());
        self::assertSame(0, $trend->repositoryCount);
        self::assertSame(0.0, $trend->change);
        self::assertSame(TrendWindow::YearToDate, $trend->window);
    }

    public function testALibraryMeasuredAsZeroHasNothingToComparePercentagesAgainst(): void
    {
        $points = [new ReleasePoint(1, new \DateTimeImmutable('2015-01-01'), 0.0)];

        // nothing to divide by - a percentage of zero complexity says nothing
        self::assertFalse($this->calculate(TrendWindow::AllTime, $points)->hasData());
    }

    public function testItAnswersEveryWindowAtOnceInTheOrderTheyAreDeclaredIn(): void
    {
        $trends = (new TrendCalculator())->calculate(self::report(), new \DateTimeImmutable(self::NOW));

        self::assertSame(
            TrendWindow::all(),
            array_map(static fn (Trend $trend) => $trend->window, $trends)
        );
    }

    public function testAnEmptyReportAnswersEveryWindowWithoutData(): void
    {
        $trends = (new TrendCalculator())->calculate([], new \DateTimeImmutable(self::NOW));

        self::assertCount(4, $trends);

        foreach ($trends as $trend) {
            self::assertFalse($trend->hasData());
        }
    }

    /**
     * @param list<ReleasePoint> $points
     */
    private function calculate(TrendWindow $window, array $points): Trend
    {
        return (new TrendCalculator())->forWindow($points, $window, new \DateTimeImmutable(self::NOW));
    }

    /**
     * Three libraries, seen from 2026-07-31: `1` releases regularly and gets simpler, `2` released twice
     * and got hairier, `3` was submitted this spring and has no history to compare against.
     *
     * @return list<ReleasePoint>
     */
    private static function report(): array
    {
        return [
            new ReleasePoint(1, new \DateTimeImmutable('2018-01-01'), 10.0),
            new ReleasePoint(1, new \DateTimeImmutable('2024-06-01'), 8.0),
            new ReleasePoint(1, new \DateTimeImmutable('2025-12-01'), 7.0),
            new ReleasePoint(1, new \DateTimeImmutable('2026-03-01'), 6.0),
            new ReleasePoint(2, new \DateTimeImmutable('2020-01-01'), 20.0),
            new ReleasePoint(2, new \DateTimeImmutable('2026-05-01'), 25.0),
            new ReleasePoint(3, new \DateTimeImmutable('2026-04-01'), 30.0),
            new ReleasePoint(3, new \DateTimeImmutable('2026-06-01'), 33.0),
        ];
    }
}
