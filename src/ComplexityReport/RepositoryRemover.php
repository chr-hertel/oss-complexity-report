<?php

declare(strict_types=1);

namespace App\ComplexityReport;

use App\ComplexityReport\Exception\SubmissionFailed;
use App\ComplexityReport\GitHub\RepositoryIdentifier;
use App\Entity\Repository;
use App\Repository\RepositoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Takes a repository back out of the report, with everything that was measured for it.
 *
 * The counterpart of submitting: anything the dataset should not carry - a repository that turned out to
 * be a mirror, one that went private, one nobody wants to see analysed again. The working copy is left to
 * `app:repositories:clean`, which removes what no repository maps to anymore anyway.
 */
final readonly class RepositoryRemover
{
    public function __construct(
        private RepositoryRepository $repositoryRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Returns the removed repository, which still carries the releases it had - the caller usually wants
     * to say how much of the report just went away.
     *
     * @throws SubmissionFailed when the input does not identify a repository of this report
     */
    public function remove(string $input): Repository
    {
        $identifier = (string) RepositoryIdentifier::fromInput($input);
        $repository = $this->repositoryRepository->findOneByName($identifier);

        if (!$repository instanceof Repository) {
            throw SubmissionFailed::notSubmitted($identifier);
        }

        // the tags are mapped without cascading deletes - releases outlive an analysis, not a repository
        foreach ($repository->getTags() as $tag) {
            $this->entityManager->remove($tag);
        }

        $project = $repository->getProject();
        // read while the row is still there - a project reached through its repository is a lazy proxy,
        // and initializing one after its deletion throws instead of returning what it used to hold
        $vendor = $project->getVendor();

        $this->entityManager->remove($repository);
        $this->entityManager->flush();

        // projects are derived from what was submitted, so a vendor without repositories is nothing to show.
        // Counted rather than read off the project: its collection still holds what was just deleted.
        if (0 === $this->repositoryRepository->count(['project' => $project])) {
            $this->entityManager->remove($project);
            $this->entityManager->flush();

            $this->logger->info(sprintf('Removed project %s along with its last repository', $vendor));
        }

        $this->logger->info(sprintf('Removed repository %s from the report', $identifier));

        return $repository;
    }
}
