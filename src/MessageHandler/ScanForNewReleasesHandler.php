<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ScanForNewReleases;
use App\Message\ScanRepository;
use App\Repository\RepositoryRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Fans the nightly run out into one message per repository, so a single unreachable remote cannot
 * stall the check for all the others.
 */
#[AsMessageHandler]
final readonly class ScanForNewReleasesHandler
{
    public function __construct(
        private RepositoryRepository $repositoryRepository,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ScanForNewReleases $message): void
    {
        $ids = $this->repositoryRepository->findAllIds();

        foreach ($ids as $id) {
            $this->messageBus->dispatch(new ScanRepository($id));
        }

        $this->logger->info(sprintf('Queued %d repositories for a release scan', count($ids)));
    }
}
