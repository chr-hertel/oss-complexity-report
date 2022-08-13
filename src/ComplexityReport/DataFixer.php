<?php

declare(strict_types=1);

namespace App\ComplexityReport;

use App\ComplexityReport\DataFixer\FixerInterface;
use Psr\Log\LoggerInterface;

final class DataFixer
{
    /**
     * @param FixerInterface[]|iterable $fixers
     */
    public function __construct(private iterable $fixers, private LoggerInterface $logger)
    {
    }

    public function fixData(): void
    {
        foreach ($this->fixers as $fixer) {
            $this->logger->info(sprintf('Executing fixer %s', get_class($fixer)));
            $fixer->fixData();
        }
    }
}
