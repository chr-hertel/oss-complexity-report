<?php

declare(strict_types=1);

namespace App\ComplexityReport;

class Analysis
{
    private int $linesOfCode;
    private float $averageComplexity;

    public function __construct(int $linesOfCode, float $averageComplexity)
    {
        $this->linesOfCode = $linesOfCode;
        $this->averageComplexity = $averageComplexity;
    }

    public function getLinesOfCode(): int
    {
        return $this->linesOfCode;
    }

    public function getAverageComplexity(): float
    {
        return $this->averageComplexity;
    }
}
