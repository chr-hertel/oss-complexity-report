<?php

declare(strict_types=1);

namespace App\Command;

use App\ComplexityReport\StatisticsLoader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('app:statistics', 'Counts what the report carries')]
final readonly class StatisticsCommand
{
    public function __construct(
        private StatisticsLoader $statistics,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $io->title('Complexity Report Statistics');

        $io->text('Analysed complexity of ...');
        $io->newLine();

        $statistics = $this->statistics->load();

        $io->listing([
            sprintf('<options=bold>%d</> repositories', $statistics->repositoryCount),
            sprintf('<options=bold>%d</> organizations', $statistics->organizationCount),
            sprintf('<options=bold>%s</> tags', number_format($statistics->tagCount)),
            sprintf('<options=bold>%s</> lines of code', number_format($statistics->linesOfCode)),
        ]);

        $io->success('Done');

        return 0;
    }
}
