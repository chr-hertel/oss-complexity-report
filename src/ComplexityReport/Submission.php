<?php

declare(strict_types=1);

namespace App\ComplexityReport;

use App\Entity\Repository;

/**
 * The outcome of a submission: the repository it points at, and whether this submission is what queued it.
 *
 * Submitting something the report already carries is not a failure - it is how people look a repository up,
 * so the caller sends them to its page instead of rejecting them.
 */
final readonly class Submission
{
    private function __construct(
        public Repository $repository,
        public bool $queued,
    ) {
    }

    public static function queued(Repository $repository): self
    {
        return new self($repository, true);
    }

    public static function known(Repository $repository): self
    {
        return new self($repository, false);
    }
}
