<?php

declare(strict_types=1);

namespace App\ComplexityReport;

/**
 * Every measured release of one repository, reduced by the database to the five facts a ranking reads.
 *
 * The report carries twenty thousand releases and a card names three of them, so picking those three is
 * the engine's job: {@see \App\Repository\TagRepository::findReleaseSummaries()} groups the releases per
 * repository and returns one of these per row. What it does not do is the arithmetic - how a change in
 * average complexity is counted, and what a missing baseline means, are rules of the report rather than
 * of the table, so they stay in {@see RankedRepository} where they are unit tested.
 */
final readonly class ReleaseSummary
{
    public function __construct(
        public int $repository,
        public int $releaseCount,
        /** The oldest measured release - what the evolution of the whole repository is counted from. */
        public string $firstName,
        public \DateTimeImmutable $firstCreated,
        public float $firstComplexity,
        /** Average cyclomatic complexity of the latest measured release. */
        public float $complexity,
        /** Lines of code of the latest measured release. */
        public int $linesOfCode,
        /**
         * The average complexity the repository stood at when the recent window opened - `null` when it
         * had not been measured yet back then, which is nothing to compare against rather than a zero.
         */
        public ?float $baseline,
    ) {
    }
}
