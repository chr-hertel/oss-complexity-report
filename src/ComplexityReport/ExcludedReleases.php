<?php

declare(strict_types=1);

namespace App\ComplexityReport;

use App\Entity\Repository;

/**
 * Releases that are proper minor releases but do not belong in the chart.
 *
 * Deleting them was not enough to keep them out: a release that is not stored is a release
 * `ReleaseScanner` reports as missing, so every night put back what had been removed - and paid for a
 * clone doing it. They are skipped while scanning instead, which makes this list the single place that
 * decides what is measured, and `DataFixer\ExcludedReleaseFixer` only clears what was already measured
 * when the list grew.
 */
final readonly class ExcludedReleases
{
    /**
     * Laravel released these minors out of order, which drags the line of the repository sideways, and
     * everything PHPUnit tagged before 3.0 sits on the same 2006-07-04 import date, which puts eight
     * releases on one day at the very left of the chart. Both are the tag itself lying about when the
     * code was written - unlike the Laminas import, where git still knows the date the fixer restores.
     *
     * @var array<string, list<string>>
     */
    private const RELEASES = [
        'laravel/framework' => ['v6.19.0', 'v6.20.0', 'v7.29.0', 'v7.30.0'],
        'sebastianbergmann/phpunit' => ['1.0.0', '1.1.0', '1.2.0', '1.3.0', '2.0.0', '2.1.0', '2.2.0', '2.3.0'],
    ];

    public function contains(Repository $repository, GitTag $tag): bool
    {
        return in_array($tag->getName(), $this->of($repository->getName()), true);
    }

    /**
     * The repository is addressed by the slug it carries on github.com, where the case does not matter.
     *
     * @return list<string>
     */
    public function of(string $repositoryName): array
    {
        foreach (self::RELEASES as $name => $releases) {
            if (0 === strcasecmp($name, $repositoryName)) {
                return $releases;
            }
        }

        return [];
    }

    /**
     * @return array<string, list<string>>
     */
    public function all(): array
    {
        return self::RELEASES;
    }
}
