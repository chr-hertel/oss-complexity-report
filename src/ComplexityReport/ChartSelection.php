<?php

declare(strict_types=1);

namespace App\ComplexityReport;

use App\Entity\Repository;

/**
 * What the chart screen was opened with: the repositories it draws, and everything else that could be
 * added to them.
 *
 * The screen does not know how it was reached and does not name itself after it. What it is called
 * follows from what is in the chart, by one rule that holds for every way it can be filled - the
 * repository itself while it is the only one, a count as soon as there are more. The browser keeps that
 * name up to date while repositories are added and removed, which is why the rule has to be this small.
 */
final readonly class ChartSelection
{
    /**
     * @param list<Repository> $selected the repositories the chart was opened with, in the order it draws them
     * @param list<Repository> $analysed everything that carries releases, most starred first
     */
    private function __construct(
        private array $selected,
        private array $analysed,
    ) {
    }

    /**
     * The chart nobody picked anything for.
     *
     * @param list<Repository> $analysed
     */
    public static function mostStarred(array $analysed, int $limit): self
    {
        return new self(array_slice($analysed, 0, $limit), $analysed);
    }

    /**
     * @param list<Repository> $selected
     * @param list<Repository> $analysed
     */
    public static function of(array $selected, array $analysed): self
    {
        return new self($selected, $analysed);
    }

    /**
     * The lines the chart draws - a repository that was just submitted has no releases to plot yet.
     *
     * @return list<Repository>
     */
    public function getSeries(): array
    {
        return array_values(array_filter($this->selected, static fn (Repository $repository) => $repository->hasData()));
    }

    /**
     * How many releases the chart is drawn from, across every line in it - what the strip under the
     * chart says it is made of, next to how many lines that is. The browser recounts it whenever a line
     * is added or taken out, so this is only what the page opens on.
     */
    public function getReleaseCount(): int
    {
        return array_sum(array_map(
            static fn (Repository $repository) => $repository->getReleaseCount(),
            $this->getSeries(),
        ));
    }

    /**
     * Everything that can be picked, what is drawn first: the position of an option is the colour of its
     * line, so the repositories the page was opened with keep the order they were asked for.
     *
     * @return list<Repository>
     */
    public function getOptions(): array
    {
        $series = $this->getSeries();
        $rest = array_filter($this->analysed, static fn (Repository $repository) => !\in_array($repository, $series, true));

        return array_merge($series, array_values($rest));
    }

    /**
     * Selected repositories that are still waiting for a worker - queued, or halfway through their
     * releases. They are what the status above the chart is about.
     *
     * @return list<Repository>
     */
    public function getWaiting(): array
    {
        return array_values(array_filter($this->selected, static fn (Repository $repository) => !$repository->isAnalysed()));
    }

    public function getHeadline(): string
    {
        $subjects = $this->getSubjects();

        return match (\count($subjects)) {
            0 => 'No repositories',
            1 => $subjects[0]->getName(),
            default => sprintf('%d repositories', \count($subjects)),
        };
    }

    /**
     * The repository on github.com while the chart is of exactly one - the same link the browser builds
     * from the slug when the selection changes, since a slug is all a github.com address is.
     */
    public function getUrl(): ?string
    {
        $subjects = $this->getSubjects();

        return 1 === \count($subjects) ? $subjects[0]->getUrl() : null;
    }

    /**
     * What the chart is of: the lines it draws, or - while it has none - what it is waiting for, which
     * is the one state the browser cannot rename, since nothing can be picked in it.
     *
     * @return list<Repository>
     */
    private function getSubjects(): array
    {
        return $this->getSeries() ?: $this->getWaiting();
    }
}
