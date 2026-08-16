<?php

declare(strict_types=1);

namespace App\Tests\ComplexityReport\Metric;

use App\ComplexityReport\Metric\Measurement;
use App\ComplexityReport\Metric\Metric;
use PHPUnit\Framework\TestCase;

final class MeasurementTest extends TestCase
{
    public function testACountIsReadAsAWholeNumber(): void
    {
        $measurement = new Measurement(['loc' => 44913, 'classes' => 448]);

        self::assertSame(44913, $measurement->value(Metric::LinesOfCode));
        self::assertSame(448, $measurement->value(Metric::Classes));
    }

    /**
     * The report is read to two decimals, which is where its trends live: 4.81 and 4.80 are a release
     * that got simpler, and rounding either to 5 is the same report as no report.
     */
    public function testAnAverageIsReadToTheDecimalsItIsWrittenWith(): void
    {
        $measurement = new Measurement(['classCcnAvg' => 4.8056768558951966, 'ccnByLloc' => 0.1429035137547675]);

        self::assertSame(4.81, $measurement->value(Metric::Complexity));
        // the one number small enough to need a third decimal
        self::assertSame(0.143, $measurement->value(Metric::ComplexityPerLine));
    }

    /**
     * A release measured by something that counted less than phploc counts is a dash in the report, not
     * a zero: a codebase with no classes at all is a measurement of 0, and there is a difference.
     */
    public function testANumberTheMeasurementDoesNotCarryIsNothing(): void
    {
        $measurement = new Measurement(['loc' => 100]);

        self::assertNull($measurement->value(Metric::Classes));
        self::assertNull($measurement->share(Metric::CommentLines));
    }

    public function testAPartIsReadAsAShareOfItsWhole(): void
    {
        $measurement = new Measurement(['loc' => 44913, 'cloc' => 11835]);

        self::assertSame(26.35, $measurement->share(Metric::CommentLines));
    }

    public function testAWholeIsAShareOfNothing(): void
    {
        $measurement = new Measurement(['loc' => 44913, 'cloc' => 11835]);

        self::assertNull($measurement->share(Metric::LinesOfCode));
    }

    /**
     * Half the report is a percentage of something that can be zero - a release without a single
     * function has no share of named ones, and dividing by it would be an error rather than a report.
     */
    public function testNothingIsNoShareOfNothing(): void
    {
        $measurement = new Measurement(['functions' => 0, 'namedFunctions' => 0]);

        self::assertNull($measurement->share(Metric::NamedFunctions));
    }

    public function testALineOfTheChartCarriesOnlyTheNumbersItIsDrawnAs(): void
    {
        $measurement = new Measurement(['loc' => 44913, 'classCcnAvg' => 4.8056768558951966, 'classes' => 448]);

        self::assertSame(
            ['complexity' => 4.81, 'loc' => 44913],
            $measurement->values([Metric::Complexity, Metric::LinesOfCode]),
        );
    }

    public function testTheInterpretedMeasurementCarriesEveryMetricAndOnlyTheSharesThereAre(): void
    {
        $interpreted = (new Measurement(['loc' => 200, 'cloc' => 50]))->interpreted();

        self::assertCount(\count(Metric::cases()), $interpreted['values']);
        self::assertSame(200, $interpreted['values']['loc']);
        self::assertNull($interpreted['values']['classes']);
        self::assertSame(['comment-lines' => 25.0], $interpreted['shares']);
    }
}
