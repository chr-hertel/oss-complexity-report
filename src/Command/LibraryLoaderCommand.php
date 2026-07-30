<?php

declare(strict_types=1);

namespace App\Command;

use App\ComplexityReport\LibraryLoader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('app:libraries:load')]
final readonly class LibraryLoaderCommand
{
    public function __construct(
        private LibraryLoader $libraryLoader,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $io->title('Load Libraries from packagist.org');

        $this->libraryLoader->load();

        $io->success('Done');

        return 0;
    }
}
