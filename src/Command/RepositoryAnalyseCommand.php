<?php

declare(strict_types=1);

namespace App\Command;

use App\ComplexityReport\Exception\SubmissionFailed;
use App\ComplexityReport\GitHub\RepositoryIdentifier;
use App\ComplexityReport\RepositoryAnalyser;
use App\ComplexityReport\WorkingCopyLock;
use App\Entity\Repository;
use App\Message\AnalyseRepository;
use App\Repository\RepositoryRepository;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Measures one named repository, in this process.
 *
 * Everything else that analyses goes through the queue: submitting dispatches, the nightly scan dispatches
 * what released. That is right for a report that fills itself and wrong for the one repository that does
 * not get through - a worker measures where nobody watches, and a repository that dies mid-analysis is
 * handed back to the transport and dies again on the next delivery. This is the same analysis run where it
 * can be watched, given a memory limit and stopped.
 */
#[AsCommand('app:repository:analyse', 'Measures the releases of a repository that are missing from the report')]
final readonly class RepositoryAnalyseCommand
{
    public function __construct(
        private RepositoryRepository $repositoryRepository,
        private RepositoryAnalyser $repositoryAnalyser,
        private WorkingCopyLock $workingCopyLock,
        private MessageBusInterface $messageBus,
    ) {
    }

    /**
     * @param string[] $repositories
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Argument('One or more repositories the report already carries, e.g. "moodle/moodle"')]
        array $repositories,
        #[Option('Queue the analysis for a worker instead of running it here')]
        bool $queue = false,
    ): int {
        $io->title('Analysing repositories');

        $failed = false;

        foreach ($repositories as $submitted) {
            try {
                $repository = $this->load($submitted);
            } catch (SubmissionFailed $exception) {
                $io->error($exception->getMessage());
                $failed = true;
                continue;
            }

            if ($queue) {
                $this->messageBus->dispatch(new AnalyseRepository($repository->getId()));
                $io->text(sprintf(' * <options=bold>%s</> queued for analysis', $repository->getName()));

                continue;
            }

            $failed = !$this->analyse($io, $repository) || $failed;
        }

        if ($queue) {
            $io->success('Queued - run messenger:consume async to work it off.');
        }

        return $failed ? 1 : 0;
    }

    /**
     * @throws SubmissionFailed when the input does not identify a repository of this report
     */
    private function load(string $input): Repository
    {
        $identifier = (string) RepositoryIdentifier::fromInput($input);
        $repository = $this->repositoryRepository->findOneByName($identifier);

        if (!$repository instanceof Repository) {
            // analysing is for what is in the report already - submitting is how something gets in
            throw SubmissionFailed::notSubmitted($identifier);
        }

        return $repository;
    }

    private function analyse(SymfonyStyle $io, Repository $repository): bool
    {
        $io->section($repository->getName());

        // the same lock a worker takes: two checkouts of one working copy measure each other's code
        $lock = $this->workingCopyLock->create($repository);

        if (!$lock->acquire()) {
            $io->warning(sprintf('%s is being analysed already - stop the worker holding it or wait.', $repository->getName()));

            return false;
        }

        try {
            $measured = $this->repositoryAnalyser->analyse($repository);
        } finally {
            $lock->release();
        }

        $io->text(sprintf('Measured %d new release(s), peak memory %d MB.', $measured, intdiv(memory_get_peak_usage(true), 1024 * 1024)));

        return true;
    }
}
