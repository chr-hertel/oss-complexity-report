<?php

declare(strict_types=1);

namespace App\ComplexityReport;

use App\Repository\ProjectRepository;
use App\Repository\RepositoryRepository;
use App\Repository\TagRepository;

final class StatisticsLoader
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private RepositoryRepository $repositoryRepository,
        private TagRepository $tagRepository,
    ) {
    }

    public function load(): Statistics
    {
        return new Statistics(
            $this->projectRepository->count([]),
            $this->repositoryRepository->count([]),
            $this->tagRepository->count([]),
            $this->tagRepository->getLinesOfCodeSum()
        );
    }
}
