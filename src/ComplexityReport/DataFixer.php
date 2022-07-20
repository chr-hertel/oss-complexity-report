<?php

declare(strict_types=1);

namespace App\ComplexityReport;

use App\ComplexityReport\DataFixer\FixerInterface;

final class DataFixer
{
    /**
     * @param FixerInterface[]|iterable $fixers
     */
    public function __construct(private iterable $fixers)
    {
    }

    public function fixData(): void
    {
        foreach ($this->fixers as $fixer) {
            $fixer->fixData();
        }
    }
}
