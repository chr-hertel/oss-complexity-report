<?php

declare(strict_types=1);

namespace App\Command;

use App\ComplexityReport\DataFixer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('app:data:fix')]
class DataFixerCommand extends Command
{
    public function __construct(private DataFixer $dataFixer)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Fixing wrong datasets');

        $this->dataFixer->fixData();

        $io->success('Done');

        return 0;
    }
}
