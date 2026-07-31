<?php

declare(strict_types=1);

namespace App\ComplexityReport;

use App\Entity\Organization;
use App\Entity\Repository;

/**
 * What the chart screen was opened with: the repositories it draws, and everything else that could be
 * added to them.
 *
 * The screen is one page for three readings - a single repository, the repositories of one GitHub
 * account, and any comparison someone put together - so what it is called follows from what is picked
 * rather than from the route it was reached by.
 */
final readonly class ChartSelection
{
    /**
     * @param list<Repository> $selected the repositories the chart was opened with, in the order it draws them
     * @param list<Repository> $analysed everything that carries releases, most starred first
     * @param bool             $picked   whether the selection was asked for, rather than the default chart
     */
    private function __construct(
        private array $selected,
        private array $analysed,
        private bool $picked,
    ) {
    }

    /**
     * The chart nobody picked anything for.
     *
     * @param list<Repository> $analysed
     */
    public static function mostStarred(array $analysed, int $limit): self
    {
        return new self(array_slice($analysed, 0, $limit), $analysed, false);
    }

    /**
     * @param list<Repository> $selected
     * @param list<Repository> $analysed
     */
    public static function of(array $selected, array $analysed): self
    {
        return new self($selected, $analysed, true);
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

    /**
     * The single GitHub account everything selected belongs to - `null` as soon as the chart mixes owners.
     */
    public function getOwner(): ?Organization
    {
        $owner = null;

        foreach ($this->selected as $repository) {
            if (null !== $owner && $owner !== $repository->getOrganization()) {
                return null;
            }

            $owner = $repository->getOrganization();
        }

        return $owner;
    }

    /**
     * What kind of thing the headline names.
     */
    public function getEyebrow(): string
    {
        return match (true) {
            !$this->picked => 'Overview',
            1 === \count($this->selected) => 'Repository',
            null !== $this->getOwner() => 'GitHub owner',
            default => 'Comparison',
        };
    }

    public function getHeadline(): string
    {
        if (!$this->picked) {
            return 'Most starred repositories';
        }

        if (1 === \count($this->selected)) {
            return $this->selected[0]->getName();
        }

        return $this->getOwner()?->getLogin() ?? sprintf('%d repositories compared', \count($this->selected));
    }

    /**
     * The page on github.com behind the headline, when it names something that has one.
     */
    public function getUrl(): ?string
    {
        if (!$this->picked) {
            return null;
        }

        if (1 === \count($this->selected)) {
            return $this->selected[0]->getUrl();
        }

        return $this->getOwner()?->getUrl();
    }
}
