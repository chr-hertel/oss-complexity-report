<?php

declare(strict_types=1);

namespace App\ComplexityReport;

use App\Repository\LibraryRepository;
use App\Repository\ProjectRepository;
use App\Repository\TagRepository;

class StatisticsLoader
{
    private ProjectRepository $projectRepository;
    private LibraryRepository $libraryRepository;
    private TagRepository $tagRepository;

    public function __construct(
        ProjectRepository $projectRepository,
        LibraryRepository $libraryRepository,
        TagRepository $tagRepository
    ) {
        $this->projectRepository = $projectRepository;
        $this->libraryRepository = $libraryRepository;
        $this->tagRepository = $tagRepository;
    }

    public function load(): Statistics
    {
        return new Statistics(
            $this->projectRepository->count([]),
            $this->libraryRepository->count([]),
            $this->tagRepository->count([]),
            $this->tagRepository->getLinesOfCodeSum()
        );
    }
}
