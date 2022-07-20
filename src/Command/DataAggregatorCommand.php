<?php

declare(strict_types=1);

namespace App\Command;

use App\ComplexityReport\DataAggregator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('app:data:aggregate')]
final class DataAggregatorCommand extends Command
{
    public function __construct(private DataAggregator $dataAggregator)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Aggregating data for OSS projects');

        $this->dataAggregator->aggregate();

        $io->success('Done');

        return 0;
    }
}
