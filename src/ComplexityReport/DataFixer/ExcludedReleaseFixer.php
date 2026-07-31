<?php

declare(strict_types=1);

namespace App\ComplexityReport\DataFixer;

use App\ComplexityReport\ExcludedReleases;
use App\Entity\Repository;
use App\Repository\RepositoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Removes releases that were measured before `ExcludedReleases` left them out.
 *
 * Nothing re-adds them anymore: the scanner skips what this list carries, so this runs once per
 * dataset that predates an entry rather than against a report that keeps growing them back.
 */
final class ExcludedReleaseFixer implements FixerInterface
{
    public function __construct(
        private ExcludedReleases $excludedReleases,
        private RepositoryRepository $repositoryRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    public function fixData(): void
    {
        foreach ($this->excludedReleases->all() as $name => $releases) {
            // repositories are submitted by users now, so none of these is necessarily part of the report
            $repository = $this->repositoryRepository->findOneByName($name);

            if (!$repository instanceof Repository) {
                $this->logger->info(sprintf('Repository %s was not submitted, nothing to fix.', $name));

                continue;
            }

            $this->removeReleases($repository, $releases);
        }

        $this->entityManager->flush();
    }

    /**
     * @param list<string> $releases
     */
    private function removeReleases(Repository $repository, array $releases): void
    {
        foreach ($repository->getTags() as $tag) {
            if (!in_array($tag->getName(), $releases, true)) {
                continue;
            }

            $this->logger->info(sprintf('Removing excluded release %s of %s', $tag->getName(), $repository->getName()));

            $this->entityManager->remove($tag);
        }
    }
}
