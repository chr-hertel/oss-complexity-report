<?php

declare(strict_types=1);

namespace App\ComplexityReport\Trend;

/**
 * One sample of {@see Trend::$series} - where the average complexity of the libraries a window compares
 * stood on a given day.
 *
 * It is not a release: a sample is the mean over every library the window carries, each of them
 * represented by the last release it had at that point. Which is why the report can have a line for a
 * day nothing was released on.
 */
final readonly class TrendPoint
{
    public function __construct(
        public \DateTimeImmutable $at,
        public float $complexity,
    ) {
    }
}
