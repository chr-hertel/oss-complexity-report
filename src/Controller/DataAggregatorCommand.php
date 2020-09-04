<?php

declare(strict_types=1);

namespace App\Controller;

use App\ComplexityReport\DataAggregator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DataAggregatorCommand extends Command
{
    protected static $defaultName = 'app:data:aggregate';

    private DataAggregator $dataAggregator;

    public function __construct(DataAggregator $dataAggregator)
    {
        $this->dataAggregator = $dataAggregator;

        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Aggregating data for OSS projects');

        $this->dataAggregator->aggregate();

        $io->success('Done');

        return 0;
    }
}
