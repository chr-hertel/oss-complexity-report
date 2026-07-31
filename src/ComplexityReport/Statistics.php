<?php

declare(strict_types=1);

namespace App\ComplexityReport;

final class Statistics
{
    public function __construct(
        public readonly int $projectCount,
        public readonly int $repositoryCount,
        public readonly int $tagCount,
        public readonly int $linesOfCode,
    ) {
    }

    public function getProjectCount(): int
    {
        return $this->projectCount;
    }

    public function getRepositoryCount(): int
    {
        return $this->repositoryCount;
    }

    public function getTagCount(): int
    {
        return $this->tagCount;
    }

    public function getLinesOfCode(): int
    {
        return $this->linesOfCode;
    }
}
