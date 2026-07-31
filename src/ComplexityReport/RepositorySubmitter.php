<?php

declare(strict_types=1);

namespace App\ComplexityReport;

use App\ComplexityReport\Exception\SubmissionFailed;
use App\ComplexityReport\GitHub\GitHubClient;
use App\ComplexityReport\GitHub\RepositoryIdentifier;
use App\Entity\Project;
use App\Entity\Repository;
use App\Message\AnalyseRepository;
use App\Repository\ProjectRepository;
use App\Repository\RepositoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

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
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
    ) {
    }

    public function submit(string $input): Submission
    {
        $submitted = RepositoryIdentifier::fromInput($input);

        // a repository the report already carries costs neither API quota nor a second row
        $known = $this->repositoryRepository->findOneByName((string) $submitted);

        if (null !== $known) {
            return Submission::known($known);
        }

        $data = $this->client->getRepository($submitted);
        $identifier = (string) $data->identifier;

        // github.com resolves names case insensitively, so what it returns can differ from what was pasted
        $known = $this->repositoryRepository->findOneByName($identifier);

        if (null !== $known) {
            return Submission::known($known);
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

        // dispatched after the flush, the handler looks the repository up by the id assigned here
        $this->messageBus->dispatch(new AnalyseRepository($repository->getId()));

        $this->logger->info(sprintf('Submitted repository %s for analysis', $identifier));

        return Submission::queued($repository);
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
