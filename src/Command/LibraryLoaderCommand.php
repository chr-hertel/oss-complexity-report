<?php

declare(strict_types=1);

namespace App\Command;

use App\ComplexityReport\LibraryLoader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class LibraryLoaderCommand extends Command
{
    protected static $defaultName = 'app:libraries:load';

    private LibraryLoader $libraryLoader;

    public function __construct(LibraryLoader $libraryLoader)
    {
        $this->libraryLoader = $libraryLoader;

        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Load Libraries from packagist.org');

        $this->libraryLoader->load();

        $io->success('Done');

        return 0;
    }
}
