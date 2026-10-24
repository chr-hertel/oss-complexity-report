<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\ComplexityReport\RepositoryAnalyser;
use App\ComplexityReport\WorkingCopyLock;
use App\Entity\Repository;
use App\Message\AnalyseRepository;
use App\Repository\RepositoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

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
        private WorkingCopyLock $workingCopyLock,
        private EntityManagerInterface $entityManager,
        private HubInterface $sentry,
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
        $lock = $this->workingCopyLock->create($repository);

        if (!$lock->acquire()) {
            // being busy is not a failure, so this deliberately retries beyond max_retries - the lock is
            // held by a live worker (flock) and released as soon as it is done or dies
            throw new RecoverableMessageHandlingException(sprintf('Repository %s is being analysed already', $repository->getName()), retryDelay: self::RETRY_DELAY);
        }

        try {
            if ($repository->isStuck()) {
                // the failure transport is where this ends up, reported once instead of on every delivery
                throw new UnrecoverableMessageHandlingException(sprintf('Repository %s did not finish its last %d analyses - measure it with app:repository:analyse', $repository->getName(), Repository::MAX_UNFINISHED_ANALYSES));
            }

            $repository->startAnalysis();
            $this->entityManager->flush();

            // a worker that runs out of memory leaves nothing but phploc frames - this says what it was measuring
            $this->sentry->configureScope(static function (Scope $scope) use ($repository): void {
                $scope->setTag('repository', $repository->getName());
            });

            $measured = $this->repositoryAnalyser->analyse($repository);
        } finally {
            $this->sentry->configureScope(static function (Scope $scope): void {
                $scope->removeTag('repository');
            });
            $lock->release();
        }

        $this->logger->info(sprintf('Measured %d new release(s) of %s', $measured, $repository->getName()));
    }
}
