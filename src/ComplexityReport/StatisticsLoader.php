<?php

declare(strict_types=1);

namespace App\ComplexityReport;

use App\Repository\OrganizationRepository;
use App\Repository\RepositoryRepository;
use App\Repository\TagRepository;

final class StatisticsLoader
{
    public function __construct(
        private OrganizationRepository $organizationRepository,
        private RepositoryRepository $repositoryRepository,
        private TagRepository $tagRepository,
    ) {
    }

    public function load(): Statistics
    {
        return new Statistics(
            $this->organizationRepository->count([]),
            $this->repositoryRepository->count([]),
            $this->tagRepository->count([]),
            $this->tagRepository->getLinesOfCodeSum()
        );
    }
}
