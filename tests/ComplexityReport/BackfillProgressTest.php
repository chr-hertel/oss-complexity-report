<?php

declare(strict_types=1);

namespace App\Tests\ComplexityReport;

use App\ComplexityReport\BackfillProgress;
use PHPUnit\Framework\TestCase;

final class BackfillProgressTest extends TestCase
{
    public function testItReadsTheReleasesThatCarryTheirOutput(): void
    {
        $progress = self::progress(releases: 1000, missingReleases: 250, incompleteRepositories: 12);

        self::assertSame(750, $progress->storedReleases());
        self::assertSame(75.0, $progress->share());
        self::assertFalse($progress->isComplete());
    }

    public function testItIsCompleteWhenNoReleaseIsMissing(): void
    {
        $progress = self::progress(releases: 1000, missingReleases: 0, incompleteRepositories: 0);

        self::assertTrue($progress->isComplete());
        self::assertSame(100.0, $progress->share());
        self::assertSame(0, $progress->runsLeft(10));
    }

    /**
     * A report without a single measured release is not a finished backfill - it is a report with
     * nothing to back fill, and a share of 100% would read as the former.
     */
    public function testAnEmptyReportIsNotDone(): void
    {
        $progress = self::progress(releases: 0, missingReleases: 0, incompleteRepositories: 0);

        self::assertSame(0.0, $progress->share());
    }

    /**
     * The run is rationed in repositories, so what is left is counted in them: a repository missing two
     * hundred releases is one clone, like the one missing a single release.
     */
    public function testItCountsTheRunsLeftInRepositories(): void
    {
        $progress = self::progress(releases: 5000, missingReleases: 4200, incompleteRepositories: 41);

        self::assertSame(5, $progress->runsLeft(10));
        self::assertSame(41, $progress->runsLeft(1));
    }

    private static function progress(int $releases, int $missingReleases, int $incompleteRepositories): BackfillProgress
    {
        // a repository the backfill can be counted against is one that carries releases, so a total
        // below the incomplete count is not a state this can be in
        return new BackfillProgress($releases, $missingReleases, max(96, $incompleteRepositories), $incompleteRepositories, []);
    }
}
