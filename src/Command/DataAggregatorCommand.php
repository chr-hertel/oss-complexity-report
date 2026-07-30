<?php

declare(strict_types=1);

namespace App\Command;

use App\ComplexityReport\DataAggregator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('app:data:aggregate')]
final readonly class DataAggregatorCommand
{
    public function __construct(
        private DataAggregator $dataAggregator,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $io->title('Aggregating data for OSS projects');

        $this->dataAggregator->aggregate();

        $io->success('Done');

        return 0;
    }
}
