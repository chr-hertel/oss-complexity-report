<?php

declare(strict_types=1);

namespace App\ComplexityReport;

use App\Repository\RepositoryRepository;
use App\Repository\TagRepository;

final readonly class BackfillProgressLoader
{
    public function __construct(
        private RepositoryRepository $repositoryRepository,
        private TagRepository $tagRepository,
    ) {
    }

    /**
     * @param int $next how many of the repositories in line to name
     */
    public function load(int $next): BackfillProgress
    {
        return new BackfillProgress(
            $this->tagRepository->count([]),
            $this->tagRepository->countMissingMetrics(),
            $this->repositoryRepository->count([]),
            $this->repositoryRepository->countIncomplete(),
            $this->repositoryRepository->findIncomplete($next),
        );
    }
}
