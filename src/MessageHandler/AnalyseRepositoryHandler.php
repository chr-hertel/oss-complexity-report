<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\ComplexityReport\RepositoryAnalyser;
use App\Message\AnalyseRepository;
use App\Repository\RepositoryRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;

#[AsMessageHandler]
final readonly class AnalyseRepositoryHandler
{
    /**
     * How long to wait for a working copy that is busy, in milliseconds.
     */
    private const int RETRY_DELAY = 300_000;

    public function __construct(
        private RepositoryRepository $repositoryRepository,
        private RepositoryAnalyser $repositoryAnalyser,
        private LockFactory $lockFactory,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(AnalyseRepository $message): void
    {
        $repository = $this->repositoryRepository->find($message->repositoryId);

        if (null === $repository) {
            $this->logger->warning(sprintf('Cannot analyse repository %d, it is gone', $message->repositoryId));

            return;
        }

        // checking out a tag rewrites the working copy, so two workers on the same repository would
        // measure whatever the other one checked out last - wrong numbers instead of a visible failure
        $lock = $this->lockFactory->createLock(sprintf('analyse-%s', $repository->getLocalPath()));

        if (!$lock->acquire()) {
            // being busy is not a failure, so this deliberately retries beyond max_retries - the lock is
            // held by a live worker (flock) and released as soon as it is done or dies
            throw new RecoverableMessageHandlingException(sprintf('Repository %s is being analysed already', $repository->getName()), retryDelay: self::RETRY_DELAY);
        }

        try {
            $measured = $this->repositoryAnalyser->analyse($repository);
        } finally {
            $lock->release();
        }

        $this->logger->info(sprintf('Measured %d new release(s) of %s', $measured, $repository->getName()));
    }
}
