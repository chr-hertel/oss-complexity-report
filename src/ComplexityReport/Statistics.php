<?php

declare(strict_types=1);

namespace App\ComplexityReport;

final class Statistics
{
    public function __construct(
        public readonly int $organizationCount,
        public readonly int $repositoryCount,
        public readonly int $tagCount,
        public readonly int $linesOfCode,
    ) {
    }

    public function getOrganizationCount(): int
    {
        return $this->organizationCount;
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
