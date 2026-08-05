<?php

declare(strict_types=1);

namespace App\ComplexityReport;

final class Analysis
{
    /**
     * @param array<string, float|int> $metrics everything phploc counted, exactly as phploc returns it -
     *                                          the report plots two of those numbers and keeps the rest,
     *                                          because it is what the raw output of a release is printed
     *                                          from and re-measuring it costs the clone all over again
     */
    public function __construct(
        public readonly int $linesOfCode,
        public readonly float $averageComplexity,
        public readonly \DateTimeImmutable $created,
        public readonly array $metrics,
    ) {
    }
}
