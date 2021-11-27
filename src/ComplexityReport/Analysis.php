<?php

declare(strict_types=1);

namespace App\ComplexityReport;

final class Analysis
{
    public function __construct(
        public readonly int $linesOfCode,
        public readonly float $averageComplexity,
        public readonly \DateTimeImmutable $created,
    ) {
    }
}
