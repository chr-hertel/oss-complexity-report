<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Measure the releases of a single repository again, for the phploc output that was not kept when they
 * were first analysed.
 */
final readonly class BackfillRepositoryMetrics
{
    public function __construct(
        public int $repositoryId,
    ) {
    }
}
