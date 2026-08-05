<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\ComplexityReport\MetricsBackfiller;
use App\ComplexityReport\WorkingCopyLock;
use App\Message\BackfillRepositoryMetrics;
use App\Repository\RepositoryRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;

#[AsMessageHandler]
final readonly class BackfillRepositoryMetricsHandler
{
    /**
     * How long to wait for a working copy that is busy, in milliseconds.
     */
    private const int RETRY_DELAY = 300_000;

    public function __construct(
        private RepositoryRepository $repositoryRepository,
        private MetricsBackfiller $backfiller,
        private WorkingCopyLock $workingCopyLock,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(BackfillRepositoryMetrics $message): void
    {
        $repository = $this->repositoryRepository->find($message->repositoryId);

        if (null === $repository) {
            $this->logger->warning(sprintf('Cannot backfill repository %d, it is gone', $message->repositoryId));

            return;
        }

        // the same lock an analysis takes: this checks out tag after tag in the same working copy, and a
        // measurement against somebody else's checkout is a wrong number rather than a failure
        $lock = $this->workingCopyLock->create($repository);

        if (!$lock->acquire()) {
            // an analysis is the work the report is there for, so a backfill simply waits for it - and
            // deliberately without spending its retries, since being busy is not a failure
            throw new RecoverableMessageHandlingException(sprintf('Repository %s is busy', $repository->getName()), retryDelay: self::RETRY_DELAY);
        }

        try {
            $filled = $this->backfiller->backfill($repository);
        } finally {
            $lock->release();
        }

        $this->logger->info(sprintf('Filled in the measurement of %d release(s) of %s', $filled, $repository->getName()));
    }
}
