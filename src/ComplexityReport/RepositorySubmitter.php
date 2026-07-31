<?php

declare(strict_types=1);

namespace App\ComplexityReport;

use App\ComplexityReport\Exception\SubmissionFailed;
use App\ComplexityReport\GitHub\GitHubClient;
use App\ComplexityReport\GitHub\RepositoryIdentifier;
use App\Entity\Project;
use App\Entity\Repository;
use App\Repository\ProjectRepository;
use App\Repository\RepositoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Takes whatever identifies a GitHub repository and queues it up for analysis.
 */
final class RepositorySubmitter
{
    /**
     * Repositories don't need a composer.json, but they need to be mostly written in PHP.
     */
    private const MIN_PHP_SHARE = 0.2;

    public function __construct(
        private GitHubClient $client,
        private RepositoryRepository $repositoryRepository,
        private ProjectRepository $projectRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    public function submit(string $input): Repository
    {
        $data = $this->client->getRepository(RepositoryIdentifier::fromInput($input));
        $identifier = (string) $data->identifier;

        if (null !== $this->repositoryRepository->findOneByName($identifier)) {
            throw SubmissionFailed::alreadySubmitted($identifier);
        }

        if ($data->fork) {
            throw SubmissionFailed::forkedRepository($identifier);
        }

        if ($data->empty) {
            throw SubmissionFailed::emptyRepository($identifier);
        }

        if ($this->client->getPhpShare($data->identifier) < self::MIN_PHP_SHARE) {
            throw SubmissionFailed::noPhpRepository($identifier);
        }

        $repository = Repository::fromGitHub($data, $this->loadProject($data->identifier->owner));

        $this->entityManager->persist($repository);
        $this->entityManager->flush();

        $this->logger->info(sprintf('Submitted repository %s for analysis', $identifier));

        return $repository;
    }

    private function loadProject(string $vendor): Project
    {
        $project = $this->projectRepository->findOneByVendor($vendor);

        if (null !== $project) {
            return $project;
        }

        $project = Project::fromGitHub($this->client->getOwner($vendor));
        $this->entityManager->persist($project);

        $this->logger->info(sprintf('Added project %s', $vendor));

        return $project;
    }
}
