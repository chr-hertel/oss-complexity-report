<?php

declare(strict_types=1);

namespace App\Command;

use App\ComplexityReport\LibraryLoader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('app:libraries:load')]
class LibraryLoaderCommand extends Command
{
    public function __construct(private LibraryLoader $libraryLoader)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Load Libraries from packagist.org');

        $this->libraryLoader->load();

        $io->success('Done');

        return 0;
    }
}
