<?php

declare(strict_types=1);

namespace App\ComplexityReport;

use App\ComplexityReport\DataFixer\FixerInterface;

class DataFixer
{
    /** @var FixerInterface[]|iterable */
    private iterable $fixers;

    /**
     * @param FixerInterface[]|iterable $fixers
     */
    public function __construct(iterable $fixers)
    {
        $this->fixers = $fixers;
    }

    public function fixData(): void
    {
        foreach ($this->fixers as $fixer) {
            $fixer->fixData();
        }
    }
}
