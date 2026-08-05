<?php

declare(strict_types=1);

namespace App\ComplexityReport;

/**
 * How far the report is through re-measuring what it did not keep, as `app:metrics:status` counts it.
 *
 * Two counts of the same thing, because they answer different questions: releases say how much of the
 * report can be read as phploc printed it, repositories say how much work is left - the backfill walks
 * repositories, and one of them costs a clone whether it is missing one release or two hundred.
 */
final readonly class BackfillProgress
{
    /**
     * @param list<array{name: string, missing: int}> $next the repositories the coming runs would take,
     *                                                      in the order they would be taken
     */
    public function __construct(
        public int $releases,
        public int $missingReleases,
        public int $repositories,
        public int $incompleteRepositories,
        public array $next,
    ) {
    }

    public function storedReleases(): int
    {
        return $this->releases - $this->missingReleases;
    }

    /**
     * How much of the report carries its measurement, in percent - a report without a single release
     * is not 100% done, it has nothing to be done with.
     */
    public function share(): float
    {
        if (0 === $this->releases) {
            return 0.0;
        }

        return round(($this->storedReleases() / $this->releases) * 100, 1);
    }

    public function isComplete(): bool
    {
        return 0 === $this->missingReleases;
    }

    /**
     * How many hourly runs are left to queue what is missing. It counts the queueing and not the work:
     * a run hands its repositories to a worker, and a repository of two hundred releases outlives the
     * hour it was queued in.
     */
    public function runsLeft(int $batch): int
    {
        return (int) ceil($this->incompleteRepositories / max(1, $batch));
    }
}
