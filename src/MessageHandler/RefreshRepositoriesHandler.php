<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\ComplexityReport\RepositoryRefresher;
use App\Message\RefreshRepositories;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RefreshRepositoriesHandler
{
    public function __construct(
        private RepositoryRefresher $repositoryRefresher,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(RefreshRepositories $message): void
    {
        $this->logger->info(sprintf('Refreshed %d repositories', $this->repositoryRefresher->refresh()));
    }
}
