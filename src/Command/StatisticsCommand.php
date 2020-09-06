<?php

declare(strict_types=1);

namespace App\Command;

use App\ComplexityReport\StatisticsLoader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class StatisticsCommand extends Command
{
    protected static $defaultName = 'app:statistics';

    private StatisticsLoader $statistics;

    public function __construct(StatisticsLoader $statistics)
    {
        $this->statistics = $statistics;

        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Complexity Report Statistics');

        $io->text('Analysed complexity of ...');
        $io->newLine();

        $statistics = $this->statistics->load();

        $io->listing([
            sprintf('<options=bold>%d</> projects', $statistics->getProjectCount()),
            sprintf('<options=bold>%d</> libraries', $statistics->getLibraryCount()),
            sprintf('<options=bold>%s</> tags', number_format($statistics->getTagCount())),
            sprintf('<options=bold>%s</> lines of code', number_format($statistics->getLinesOfCode())),
        ]);

        $io->success('Done');

        return 0;
    }
}
