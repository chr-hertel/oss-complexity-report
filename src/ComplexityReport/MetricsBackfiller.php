<?php

declare(strict_types=1);

namespace App\ComplexityReport;

use App\Entity\Repository;
use App\Entity\Tag;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Measures the releases the report already carries a second time, for the part of the measurement it did
 * not keep the first time.
 *
 * Every release analysed before {@see Analysis::$metrics} existed stored two numbers, and phploc counts
 * sixty - so the raw output of those releases is not missing from a table somewhere, it was never
 * written down. Recovering it is the expensive half of an analysis all over again: clone the repository,
 * check every one of its tags out, run phploc. That is why nothing does this on demand and a handful of
 * repositories are worked off per hour instead.
 *
 * What it corrects is what was never stored. The lines of code and the average complexity a release was
 * written with stay untouched even though this measurement produces them again: they are what the chart
 * has been drawn from for years, and a backfill is not the place to move a line.
 *
 * Like every other analysis it rewrites the working copy, so the caller holds the {@see WorkingCopyLock}.
 */
final readonly class MetricsBackfiller
{
    public function __construct(
        private GitController $gitController,
        private CodeAnalyser $codeAnalyser,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return int number of releases whose measurement was filled in
     */
    public function backfill(Repository $repository): int
    {
        $missing = array_filter($repository->getTags(), static fn (Tag $tag) => !$tag->hasMetrics());

        // asked before anything is cloned: a repository that is complete costs nothing, which is what
        // makes it harmless for the hourly run to name the same repositories again while a worker is
        // still busy with them
        if ([] === $missing) {
            return 0;
        }

        $filled = 0;

        try {
            // fetches the tags along with the clone, and says which ones the remote still has: a release
            // whose tag was deleted on github.com cannot be checked out, and a checkout that fails would
            // hand the message back to the transport for that one release, forever
            $available = array_map(
                static fn (GitTag $tag) => $tag->getName(),
                $this->gitController->loadTags($repository),
            );

            foreach ($missing as $tag) {
                if (!\in_array($tag->getName(), $available, true)) {
                    $this->logger->warning(sprintf('Cannot re-measure %s %s, the tag is gone', $repository->getName(), $tag->getName()));

                    continue;
                }

                $this->logger->info(sprintf('Re-measuring %s tag %s', $repository->getName(), $tag->getName()));

                $this->gitController->checkoutTag($repository, $tag->getName());
                $tag->storeMetrics($this->codeAnalyser->analyse($repository)->metrics);

                // flushed per release, so an interrupted run keeps what it already measured and the next
                // delivery of the message picks up where it stopped
                $this->entityManager->flush();
                ++$filled;
            }
        } finally {
            // the working copy is scratch space here as well - it was cloned for this and nothing else
            $this->gitController->removeWorkingCopy($repository->getLocalPath());
        }

        return $filled;
    }
}
