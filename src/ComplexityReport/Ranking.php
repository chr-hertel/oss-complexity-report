<?php

declare(strict_types=1);

namespace App\ComplexityReport;

use App\Entity\Repository;

/**
 * The orders the start page offers for its featured repositories - stars is the one the report itself
 * is built around, the other three sort by what was actually measured.
 */
enum Ranking: string
{
    case Stars = 'stars';
    case Complexity = 'complexity';
    case Size = 'size';
    case Evolution = 'evolution';

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        return self::cases();
    }

    public function label(): string
    {
        return match ($this) {
            self::Stars => 'Most starred',
            self::Complexity => 'Highest complexity',
            self::Size => 'Largest',
            self::Evolution => 'Fastest changing',
        };
    }

    /**
     * The unit shown next to the tab label - the stars tab needs none, it is the default order.
     */
    public function hint(): ?string
    {
        return match ($this) {
            self::Stars => null,
            self::Complexity => 'Ø',
            self::Size => 'LOC',
            self::Evolution => '%',
        };
    }

    public function title(): string
    {
        return match ($this) {
            self::Stars => 'Most starred repositories',
            self::Complexity => 'Highest average complexity',
            self::Size => 'Largest codebases',
            self::Evolution => 'Biggest change over time',
        };
    }

    public function caption(): string
    {
        return match ($this) {
            self::Stars => 'The order the report itself is built around - stars decide what the chart opens with.',
            self::Complexity => 'Average cyclomatic complexity of the latest analysed release, across all methods and functions.',
            self::Size => 'Lines of code in the latest analysed release, as phploc counts them.',
            self::Evolution => 'Change in average complexity between the first and the latest analysed release - green means the codebase got simpler, red means it got hairier.',
        };
    }

    /**
     * @param list<Repository> $repositories
     *
     * @return list<Repository>
     */
    public function sort(array $repositories, int $limit): array
    {
        usort($repositories, $this->comparator());

        return array_slice($repositories, 0, $limit);
    }

    /**
     * Ties are broken by stars so a ranking never reorders itself between two requests.
     *
     * @return callable(Repository, Repository): int
     */
    private function comparator(): callable
    {
        return match ($this) {
            self::Stars => static fn (Repository $left, Repository $right) => $right->getStars() <=> $left->getStars(),
            self::Complexity => static fn (Repository $left, Repository $right) => [$right->getComplexity(), $right->getStars()] <=> [$left->getComplexity(), $left->getStars()],
            self::Size => static fn (Repository $left, Repository $right) => [$right->getLinesOfCode(), $right->getStars()] <=> [$left->getLinesOfCode(), $left->getStars()],
            self::Evolution => static fn (Repository $left, Repository $right) => [abs($right->getEvolution()), $right->getStars()] <=> [abs($left->getEvolution()), $left->getStars()],
        };
    }
}
