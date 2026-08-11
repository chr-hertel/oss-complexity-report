<?php

declare(strict_types=1);

namespace App\ComplexityReport;

use App\Repository\RepositoryRepository;
use App\Repository\TagRepository;

/**
 * Everything the start page ranks, in the order the rankings sort from.
 *
 * A card says three things about a repository that only its releases know: where it started, where it
 * stands, and where it stood twelve months ago. Reading those off the entities meant loading every
 * release of every repository - twenty thousand of them, each carrying the phploc measurement it was
 * reduced from - to print thirty six cards. So the releases never leave the database: it groups them
 * into one summary per repository, and the two queries this takes are the whole page.
 */
final readonly class RankingLoader
{
    public function __construct(
        private RepositoryRepository $repositoryRepository,
        private TagRepository $tagRepository,
    ) {
    }

    /**
     * @return list<RankedRepository>
     */
    public function load(): array
    {
        $since = new \DateTimeImmutable(RankedRepository::RECENT);

        $summaries = [];

        foreach ($this->tagRepository->findReleaseSummaries($since) as $summary) {
            $summaries[$summary->repository] = $summary;
        }

        $ranked = [];

        foreach ($this->repositoryRepository->findAnalysed() as $repository) {
            // findAnalysed() only names repositories that carry releases, and a release measured between
            // the two queries brings its own repository - so this is the empty report, not a gap
            if (!isset($summaries[$repository->getId()])) {
                continue;
            }

            $ranked[] = RankedRepository::from($repository, $summaries[$repository->getId()]);
        }

        return $ranked;
    }
}
