<?php

declare(strict_types=1);

namespace App\Command;

use App\ComplexityReport\RepositoryRefresher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('app:repositories:refresh', 'Updates stars and metadata of all submitted repositories')]
final readonly class RepositoryRefreshCommand
{
    public function __construct(
        private RepositoryRefresher $refresher,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $io->title('Refreshing repositories from github.com');

        $refreshed = $this->refresher->refresh();

        $io->success(sprintf('Refreshed %d repositories', $refreshed));

        return 0;
    }
}
