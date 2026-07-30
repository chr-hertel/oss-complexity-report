<?php

declare(strict_types=1);

namespace App\Command;

use App\ComplexityReport\DataFixer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('app:data:fix')]
final readonly class DataFixerCommand
{
    public function __construct(
        private DataFixer $dataFixer,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $io->title('Fixing wrong datasets');

        $this->dataFixer->fixData();

        $io->success('Done');

        return 0;
    }
}
