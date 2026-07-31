<?php

declare(strict_types=1);

namespace App\Command;

use App\ComplexityReport\DataAggregator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('app:data:aggregate', 'Analyses the releases of all submitted repositories')]
final readonly class DataAggregatorCommand
{
    public function __construct(
        private DataAggregator $dataAggregator,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option('Only analyse repositories that were submitted but never analysed')]
        bool $pending = false,
    ): int {
        $io->title('Aggregating data for submitted repositories');

        $analysed = $this->dataAggregator->aggregate($pending);

        $io->success(sprintf('Analysed %d repositories', $analysed));

        return 0;
    }
}
