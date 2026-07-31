<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Measure every release of a repository that is not measured yet - the expensive part of the report.
 */
final readonly class AnalyseRepository
{
    public function __construct(
        public int $repositoryId,
    ) {
    }
}
