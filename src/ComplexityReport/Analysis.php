<?php

declare(strict_types=1);

namespace App\ComplexityReport;

class Analysis
{
    private int $linesOfCode;
    private float $averageComplexity;
    private \DateTimeImmutable $created;

    public function __construct(int $linesOfCode, float $averageComplexity, \DateTimeImmutable $created)
    {
        $this->linesOfCode = $linesOfCode;
        $this->averageComplexity = $averageComplexity;
        $this->created = $created;
    }

    public function getLinesOfCode(): int
    {
        return $this->linesOfCode;
    }

    public function getAverageComplexity(): float
    {
        return $this->averageComplexity;
    }

    public function getCreated(): \DateTimeImmutable
    {
        return $this->created;
    }
}
