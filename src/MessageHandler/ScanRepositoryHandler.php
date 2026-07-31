<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\ComplexityReport\GitTag;
use App\ComplexityReport\ReleaseScanner;
use App\Message\AnalyseRepository;
use App\Message\ScanRepository;
use App\Repository\RepositoryRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class ScanRepositoryHandler
{
    public function __construct(
        private RepositoryRepository $repositoryRepository,
        private ReleaseScanner $releaseScanner,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ScanRepository $message): void
    {
        $repository = $this->repositoryRepository->find($message->repositoryId);

        if (null === $repository) {
            $this->logger->warning(sprintf('Cannot scan repository %d, it is gone', $message->repositoryId));

            return;
        }

        $releases = $this->releaseScanner->scanRemote($repository);

        if ([] === $releases) {
            return;
        }

        $this->logger->info(sprintf(
            'Queueing %s for analysis, it released %s',
            $repository->getName(),
            implode(', ', array_map(static function (GitTag $release) {
                return $release->getName();
            }, $releases))
        ));

        $this->messageBus->dispatch(new AnalyseRepository($repository->getId()));
    }
}
