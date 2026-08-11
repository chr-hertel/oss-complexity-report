<?php

declare(strict_types=1);

namespace App\ComplexityReport;

use App\Entity\Repository;

/**
 * A repository as a ranking reads it: what it is, plus the figures its card is sorted and drawn by.
 *
 * The figures used to be methods on the entity, which meant a card could only be printed by loading every
 * release of every repository - twenty thousand of them, each carrying the phploc measurement it was
 * reduced from. Which releases a card is about is a question the database answers now
 * ({@see ReleaseSummary}); what is left here is what those releases mean, so the rules stay unit tested
 * the way the trend rules are.
 */
final readonly class RankedRepository
{
    /**
     * How far back {@see self::$recentEvolution} looks - the twelve months the report calls recent, and
     * the window the summaries are asked for.
     */
    public const string RECENT = '-12 months';

    private function __construct(
        public Repository $repository,
        /** The oldest measured release, which the card names as the point the evolution is counted from. */
        public string $firstRelease,
        public \DateTimeImmutable $firstReleased,
        public int $releaseCount,
        /** Average cyclomatic complexity of the latest measured release - the figure the chart ends on. */
        public float $complexity,
        /** Lines of code of the latest measured release. */
        public int $linesOfCode,
        /** How the average complexity changed since the first release, in percent - down is simpler. */
        public float $evolution,
        /** The same figure for the last twelve months - what the "Latest Increases" ranking orders by. */
        public float $recentEvolution,
    ) {
    }

    public static function from(Repository $repository, ReleaseSummary $releases): self
    {
        return new self(
            $repository,
            $releases->firstName,
            $releases->firstCreated,
            $releases->releaseCount,
            $releases->complexity,
            $releases->linesOfCode,
            self::change($releases->firstComplexity, $releases->complexity),
            // a repository that was not measured yet when the window opened has nothing to say about it,
            // and neither has one that has not released since - there the baseline is the latest release
            null === $releases->baseline ? 0.0 : self::change($releases->baseline, $releases->complexity),
        );
    }

    /**
     * Change between two average complexities, in percent - a release without a single class has no
     * average to compare against, so it is no change rather than a division by zero.
     */
    private static function change(float $from, float $to): float
    {
        if (0.0 === $from) {
            return 0.0;
        }

        return round((($to - $from) / $from) * 100, 1);
    }
}
