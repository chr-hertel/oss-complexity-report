<?php

declare(strict_types=1);

namespace App\Tests\ComplexityReport;

use App\ComplexityReport\ComplexityLevel;
use PHPUnit\Framework\TestCase;

final class ComplexityLevelTest extends TestCase
{
    /**
     * @dataProvider complexities
     */
    public function testReadsAComplexityAsTheBandItFallsInto(float $complexity, ComplexityLevel $expected): void
    {
        self::assertSame($expected, ComplexityLevel::of($complexity));
    }

    /**
     * @return iterable<string, array{float, ComplexityLevel}>
     */
    public static function complexities(): iterable
    {
        yield 'the lowest a measured average can be' => [1.0, ComplexityLevel::Simple];
        yield 'a typical library' => [6.54, ComplexityLevel::Simple];
        yield 'the last of the first band' => [10.0, ComplexityLevel::Simple];
        // the report measures averages, so a band ends where its number does
        yield 'just past it' => [10.01, ComplexityLevel::Moderate];
        yield 'the last of the second band' => [20.0, ComplexityLevel::Moderate];
        yield 'past the second band' => [20.5, ComplexityLevel::Complex];
        yield 'the last of the third band' => [50.0, ComplexityLevel::Complex];
        yield 'past every band there is' => [50.1, ComplexityLevel::Untestable];
    }

    public function testPrintsTheWholeScaleInTheOrderItRuns(): void
    {
        $ranges = array_map(static fn (ComplexityLevel $level) => $level->range(), ComplexityLevel::cases());

        self::assertSame(['1–10', '11–20', '21–50', '> 50'], $ranges);
    }
}
