<?php

declare(strict_types=1);

namespace App\ComplexityReport;

/**
 * The orders the start page offers for its featured repositories - stars is the one the report itself
 * is built around, the other three sort by what was actually measured.
 */
enum Ranking: string
{
    case Stars = 'stars';
    case Complexity = 'complexity';
    case Size = 'size';
    case Growth = 'growth';

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
            self::Growth => 'Latest Increases',
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
            self::Growth => '%',
        };
    }

    public function title(): string
    {
        return match ($this) {
            self::Stars => 'Most starred repositories',
            self::Complexity => 'Highest average complexity',
            self::Size => 'Largest codebases',
            self::Growth => 'Biggest increase in the last 12 months',
        };
    }

    public function caption(): string
    {
        return match ($this) {
            self::Stars => 'The order the report itself is built around - stars decide what the chart opens with.',
            self::Complexity => 'Average cyclomatic complexity of the latest analysed release, across all methods and functions.',
            self::Size => 'Lines of code in the latest analysed release, as phploc counts them.',
            self::Growth => 'Repositories whose average complexity grew the most within the last 12 months, measured against the release they stood at back then - the window the figure above uses as well.',
        };
    }

    /**
     * @param list<RankedRepository> $repositories
     *
     * @return list<RankedRepository>
     */
    public function sort(array $repositories, int $limit): array
    {
        $ranked = array_values(array_filter($repositories, $this->filter()));

        usort($ranked, $this->comparator());

        return array_slice($ranked, 0, $limit);
    }

    /**
     * What a ranking has no room for. Only the increases leave anything out: a repository that got
     * simpler within the window did not increase, and neither did one that has not released in it -
     * both are 0.0 or less, which is nothing to rank.
     *
     * @return callable(RankedRepository): bool
     */
    private function filter(): callable
    {
        return match ($this) {
            self::Growth => static fn (RankedRepository $repository) => $repository->recentEvolution > 0.0,
            default => static fn (RankedRepository $repository) => true,
        };
    }

    /**
     * Ties are broken by stars so a ranking never reorders itself between two requests.
     *
     * @return callable(RankedRepository, RankedRepository): int
     */
    private function comparator(): callable
    {
        return match ($this) {
            self::Stars => static fn (RankedRepository $left, RankedRepository $right) => $right->repository->getStars() <=> $left->repository->getStars(),
            self::Complexity => static fn (RankedRepository $left, RankedRepository $right) => [$right->complexity, $right->repository->getStars()] <=> [$left->complexity, $left->repository->getStars()],
            self::Size => static fn (RankedRepository $left, RankedRepository $right) => [$right->linesOfCode, $right->repository->getStars()] <=> [$left->linesOfCode, $left->repository->getStars()],
            self::Growth => static fn (RankedRepository $left, RankedRepository $right) => [$right->recentEvolution, $right->repository->getStars()] <=> [$left->recentEvolution, $left->repository->getStars()],
        };
    }
}
