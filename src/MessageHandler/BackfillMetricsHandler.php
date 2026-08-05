<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\ComplexityReport\MetricsBackfiller;
use App\Message\BackfillMetrics;
use App\Message\BackfillRepositoryMetrics;
use App\Repository\RepositoryRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Hands the next few incomplete repositories to a worker, once an hour.
 *
 * Re-measuring a repository costs a clone and a phploc run per release, which is the whole reason the
 * output was not kept in the first place. So this is deliberately a trickle rather than a batch: the
 * report fills in over days while the queue stays free for what visitors submit, and the run does not
 * need to be resumed after a deploy because the next hour asks the same question again - the query is
 * "what is still missing", not "where did we stop".
 *
 * Naming the same repository twice is harmless: a backfill that finds nothing missing returns before it
 * clones anything.
 */
#[AsMessageHandler]
final readonly class BackfillMetricsHandler
{
    public function __construct(
        private RepositoryRepository $repositoryRepository,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(BackfillMetrics $message): void
    {
        $ids = $this->repositoryRepository->findIncompleteIds(MetricsBackfiller::BATCH);

        foreach ($ids as $id) {
            $this->messageBus->dispatch(new BackfillRepositoryMetrics($id));
        }

        $this->logger->info(sprintf('Queued %d repositories for a metrics backfill', \count($ids)));
    }
}
