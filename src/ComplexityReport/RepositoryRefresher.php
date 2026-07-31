<?php

declare(strict_types=1);

namespace App\ComplexityReport;

use App\ComplexityReport\Exception\SubmissionFailed;
use App\ComplexityReport\GitHub\GitHubClient;
use App\ComplexityReport\GitHub\RepositoryIdentifier;
use App\Repository\ProjectRepository;
use App\Repository\RepositoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Keeps the popularity of submitted repositories - and with it the order of the report - up to date.
 */
final class RepositoryRefresher
{
    public function __construct(
        private GitHubClient $client,
        private RepositoryRepository $repositoryRepository,
        private ProjectRepository $projectRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    public function refresh(): int
    {
        $refreshed = 0;

        foreach ($this->repositoryRepository->findAll() as $repository) {
            try {
                $data = $this->client->getRepository(RepositoryIdentifier::fromInput($repository->getName()), true);
            } catch (SubmissionFailed $exception) {
                $this->logger->warning(sprintf(
                    'Cannot refresh repository %s: %s',
                    $repository->getName(),
                    $exception->getMessage()
                ));
                continue;
            }

            $this->logger->info(sprintf('Refreshing %s with %d stars', $repository->getName(), $data->stars));
            $repository->update($data);
            ++$refreshed;
        }

        foreach ($this->projectRepository->findAll() as $project) {
            try {
                $project->update($this->client->getOwner($project->getVendor(), true));
            } catch (SubmissionFailed $exception) {
                $this->logger->warning(sprintf(
                    'Cannot refresh project %s: %s',
                    $project->getVendor(),
                    $exception->getMessage()
                ));
            }
        }

        $this->entityManager->flush();

        return $refreshed;
    }
}
