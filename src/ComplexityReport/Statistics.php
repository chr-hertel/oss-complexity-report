<?php

declare(strict_types=1);

namespace App\ComplexityReport;

class Statistics
{
    private int $projectCount;
    private int $libraryCount;
    private int $tagCount;
    private int $linesOfCode;

    public function __construct(int $projectCount, int $libraryCount, int $tagCount, int $linesOfCode)
    {
        $this->projectCount = $projectCount;
        $this->libraryCount = $libraryCount;
        $this->tagCount = $tagCount;
        $this->linesOfCode = $linesOfCode;
    }

    public function getProjectCount(): int
    {
        return $this->projectCount;
    }

    public function getLibraryCount(): int
    {
        return $this->libraryCount;
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
