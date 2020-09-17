<?php

declare(strict_types=1);

namespace App\Command;

use App\ComplexityReport\DataFixer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DataFixerCommand extends Command
{
    protected static $defaultName = 'app:data:fix';

    private DataFixer $dataFixer;

    public function __construct(DataFixer $dataFixer)
    {
        $this->dataFixer = $dataFixer;

        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Fixing wrong datasets');

        $this->dataFixer->fixData();

        $io->success('Done');

        return 0;
    }


}
