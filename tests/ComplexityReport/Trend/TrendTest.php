<?php

declare(strict_types=1);

namespace App\Tests\ComplexityReport\Trend;

use App\ComplexityReport\Trend\Trend;
use App\ComplexityReport\Trend\TrendWindow;
use PHPUnit\Framework\TestCase;

final class TrendTest extends TestCase
{
    /**
     * @dataProvider movements
     */
    public function testItSaysWhatTheFigureDidRatherThanRepeatingIt(float $change, string $expected): void
    {
        self::assertSame($expected, self::trend($change)->movement());
    }

    /**
     * @return iterable<string, array{float, string}>
     */
    public static function movements(): iterable
    {
        // the threshold the report colours a change by - what is printed grey is not a direction
        yield 'nothing worth a direction' => [0.0, 'held steady'];
        yield 'below a twentieth of a percent' => [0.04, 'held steady'];
        yield 'just above it' => [-0.06, 'got slightly less branchy'];
        yield 'slightly simpler' => [-1.8, 'got slightly less branchy'];
        yield 'slightly hairier' => [1.9, 'got slightly more branchy'];
        yield 'simpler' => [-6.4, 'got less branchy'];
        yield 'hairier' => [9.9, 'got more branchy'];
        yield 'much simpler' => [-14.2, 'got much less branchy'];
        yield 'much hairier' => [22.9, 'got much more branchy'];
    }

    /**
     * A window nothing reaches back to has no direction either - and it never says "held steady",
     * because holding steady is something a report with data did.
     */
    public function testAWindowWithoutDataCarriesNoSeries(): void
    {
        $trend = Trend::withoutData(TrendWindow::YearToDate);

        self::assertFalse($trend->hasData());
        self::assertSame([], $trend->series);
    }

    private static function trend(float $change): Trend
    {
        return new Trend(TrendWindow::YearToDate, 2.34, 2.30, $change, 64);
    }
}
