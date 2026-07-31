<?php

declare(strict_types=1);

namespace App\Command;

use App\ComplexityReport\GitController;
use App\ComplexityReport\WorkingCopyLock;
use App\Entity\Repository;
use App\Repository\RepositoryRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * An analysis cleans up after itself, so this is for what came before that: working copies of the
 * packagist era, of repositories that were renamed, and of workers that were killed mid-analysis.
 */
#[AsCommand('app:repositories:clean', 'Removes working copies that are not being analysed')]
final readonly class RepositoryCleanCommand
{
    public function __construct(
        private RepositoryRepository $repositoryRepository,
        private GitController $gitController,
        private WorkingCopyLock $workingCopyLock,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $io->title('Removing working copies');

        $repositories = [];

        foreach ($this->repositoryRepository->findAll() as $repository) {
            $repositories[$repository->getLocalPath()] = $repository;
        }

        $removed = 0;
        $orphaned = 0;
        $busy = 0;

        foreach ($this->gitController->listWorkingCopies() as $localPath) {
            $repository = $repositories[$localPath] ?? null;

            // nothing can be analysing a working copy that no submitted repository maps to
            if (!$repository instanceof Repository) {
                $this->gitController->removeWorkingCopy($localPath);
                ++$orphaned;
                continue;
            }

            if (!$this->remove($repository)) {
                ++$busy;
                continue;
            }

            ++$removed;
        }

        if ($busy > 0) {
            $io->note(sprintf('Kept %d working copies that are being analysed right now.', $busy));
        }

        $io->success(sprintf('Removed %d working copies and %d left behind by former repositories', $removed, $orphaned));

        return 0;
    }

    private function remove(Repository $repository): bool
    {
        $lock = $this->workingCopyLock->create($repository);

        if (!$lock->acquire()) {
            return false;
        }

        try {
            $this->gitController->removeWorkingCopy($repository->getLocalPath());
        } finally {
            $lock->release();
        }

        return true;
    }
}
