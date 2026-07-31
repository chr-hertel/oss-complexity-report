<?php

declare(strict_types=1);

namespace App\ComplexityReport\Trend;

/**
 * One measured release, reduced to what a trend is computed from - the whole report fits into a list of
 * these, which is why the calculation never needs an entity or a database.
 */
final readonly class ReleasePoint
{
    public function __construct(
        public int $repository,
        public \DateTimeImmutable $created,
        public float $averageComplexity,
    ) {
    }
}
