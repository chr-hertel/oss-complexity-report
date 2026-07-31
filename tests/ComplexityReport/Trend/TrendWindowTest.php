<?php

declare(strict_types=1);

namespace App\Tests\ComplexityReport\Trend;

use App\ComplexityReport\Trend\TrendWindow;
use PHPUnit\Framework\TestCase;

final class TrendWindowTest extends TestCase
{
    /**
     * @dataProvider starts
     */
    public function testItKnowsWhenItOpened(TrendWindow $window, ?string $expected): void
    {
        $start = $window->startedAt(new \DateTimeImmutable('2026-07-31 12:34:56'));

        self::assertSame($expected, $start?->format('Y-m-d H:i:s'));
    }

    /**
     * @return iterable<string, array{TrendWindow, string|null}>
     */
    public static function starts(): iterable
    {
        // year to date opens on new year's day, not twelve hours into it
        yield 'year to date' => [TrendWindow::YearToDate, '2026-01-01 00:00:00'];
        yield 'twelve months' => [TrendWindow::TwelveMonths, '2025-07-31 12:34:56'];
        yield 'five years' => [TrendWindow::FiveYears, '2021-07-31 12:34:56'];
        // all time has no date every library shares - it starts at their own first release
        yield 'all time' => [TrendWindow::AllTime, null];
    }

    public function testTheTurnOfTheYearLeavesYearToDateWithoutADayInIt(): void
    {
        $start = TrendWindow::YearToDate->startedAt(new \DateTimeImmutable('2026-01-01 00:30:00'));

        self::assertSame('2026-01-01 00:00:00', $start?->format('Y-m-d H:i:s'));
    }

    public function testALeapDayStillFindsItsWindowAYearBack(): void
    {
        $start = TrendWindow::TwelveMonths->startedAt(new \DateTimeImmutable('2024-02-29 00:00:00'));

        self::assertSame('2023-03-01 00:00:00', $start?->format('Y-m-d H:i:s'));
    }

    public function testEveryWindowIsNamed(): void
    {
        foreach (TrendWindow::all() as $window) {
            self::assertNotSame('', $window->label());
            self::assertNotSame('', $window->title());
        }
    }

    public function testTheWindowsAreOfferedShortestFirst(): void
    {
        self::assertSame(
            [TrendWindow::YearToDate, TrendWindow::TwelveMonths, TrendWindow::FiveYears, TrendWindow::AllTime],
            TrendWindow::all()
        );
    }
}
