<?php

declare(strict_types=1);

namespace App\Command;

use App\ComplexityReport\Exception\SubmissionFailed;
use App\ComplexityReport\GitHub\RepositoryIdentifier;
use App\ComplexityReport\MetricsBackfiller;
use App\ComplexityReport\WorkingCopyLock;
use App\Entity\Repository;
use App\Repository\RepositoryRepository;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Fills in the phploc output of releases that were measured before the report kept it, in this process.
 *
 * The hourly schedule does this on its own and needs nobody to run anything. This is the same work where
 * it can be watched: named repositories rather than the next ones in line, a memory limit if a large one
 * needs it, and a peak memory reading afterwards - which is what a backfill of a repository with hundreds
 * of releases is usually stopped by.
 */
#[AsCommand('app:metrics:backfill', 'Re-measures releases that carry no phploc output yet')]
final readonly class MetricsBackfillCommand
{
    public function __construct(
        private RepositoryRepository $repositoryRepository,
        private MetricsBackfiller $backfiller,
        private WorkingCopyLock $workingCopyLock,
    ) {
    }

    /**
     * @param string[] $repositories
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Repositories to fill in, e.g. "moodle/moodle" - the most starred incomplete ones when none are named')]
        array $repositories = [],
        #[Option('How many repositories to work off when none are named')]
        int $limit = MetricsBackfiller::BATCH,
    ): int {
        $io->title('Filling in phploc output');

        try {
            $selected = [] === $repositories ? $this->incomplete($limit) : array_map($this->load(...), $repositories);
        } catch (SubmissionFailed $exception) {
            $io->error($exception->getMessage());

            return 1;
        }

        if ([] === $selected) {
            $io->success('Every measured release carries its phploc output.');

            return 0;
        }

        $filled = 0;

        foreach ($selected as $repository) {
            $filled += $this->backfill($io, $repository);
        }

        $io->success(sprintf('Filled in %d release(s), peak memory %d MB.', $filled, intdiv(memory_get_peak_usage(true), 1024 * 1024)));

        return 0;
    }

    /**
     * @return list<Repository>
     */
    private function incomplete(int $limit): array
    {
        $ids = $this->repositoryRepository->findIncompleteIds($limit);

        return array_values(array_filter(array_map($this->repositoryRepository->find(...), $ids)));
    }

    /**
     * @throws SubmissionFailed when the input does not identify a repository of this report
     */
    private function load(string $input): Repository
    {
        $identifier = (string) RepositoryIdentifier::fromInput($input);
        $repository = $this->repositoryRepository->findOneByName($identifier);

        if (!$repository instanceof Repository) {
            throw SubmissionFailed::notSubmitted($identifier);
        }

        return $repository;
    }

    private function backfill(SymfonyStyle $io, Repository $repository): int
    {
        $io->section($repository->getName());

        // the same lock a worker takes: this rewrites the working copy tag by tag
        $lock = $this->workingCopyLock->create($repository);

        if (!$lock->acquire()) {
            $io->warning(sprintf('%s is busy - stop the worker holding it or wait.', $repository->getName()));

            return 0;
        }

        try {
            $filled = $this->backfiller->backfill($repository);
        } finally {
            $lock->release();
        }

        $io->text(sprintf('Filled in %d release(s).', $filled));

        return $filled;
    }
}
