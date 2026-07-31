<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Check a single repository for releases that are not measured yet.
 */
final readonly class ScanRepository
{
    public function __construct(
        public int $repositoryId,
    ) {
    }
}
