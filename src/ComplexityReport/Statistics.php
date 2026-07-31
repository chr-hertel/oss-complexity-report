<?php

declare(strict_types=1);

namespace App\ComplexityReport;

/**
 * What the report carries, as the start page and `app:statistics` count it.
 */
final readonly class Statistics
{
    public function __construct(
        public int $organizationCount,
        public int $repositoryCount,
        public int $tagCount,
        public int $linesOfCode,
    ) {
    }
}
