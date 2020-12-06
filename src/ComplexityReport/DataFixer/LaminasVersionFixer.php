<?php

declare(strict_types=1);

namespace App\ComplexityReport\DataFixer;

use App\Entity\Library;
use App\Repository\ProjectRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use GitWrapper\GitWrapper;
use Psr\Log\LoggerInterface;
use RuntimeException;

class LaminasVersionFixer implements FixerInterface
{
    private ProjectRepository $repository;
    private GitWrapper $gitWrapper;
    private LoggerInterface $logger;
    private EntityManagerInterface $entityManager;
    private string $repositoryPath;

    public function __construct(
        ProjectRepository $repository,
        GitWrapper $gitWrapper,
        LoggerInterface $logger,
        EntityManagerInterface $entityManager,
        string $repositoryPath
    ) {
        $this->repository = $repository;
        $this->gitWrapper = $gitWrapper;
        $this->logger = $logger;
        $this->entityManager = $entityManager;
        $this->repositoryPath = $repositoryPath;
    }

    public function fixData(): void
    {
        $laminas = $this->repository->findOneByName('Laminas');

        if (null === $laminas) {
            throw new RuntimeException('Cannot find library Laminas.');
        }

        foreach ($laminas->getLibraries() as $library) {
            $this->logger->info(sprintf('Checking %s for misplaced releases.', $library->getName()));
            $this->shiftLibraryReleases($library);
        }

        $this->entityManager->flush();
    }

    private function shiftLibraryReleases(Library $library): void
    {
        $repository = $this->gitWrapper->workingCopy($this->repositoryPath.'/'.$library->getRepositoryPath());

        foreach ($library->getTags() as $tag) {
            if ('2019-12-31' !== $tag->getCreated()->format('Y-m-d')) {
                $this->logger->info(sprintf('Skipping release %s, looks good.', $tag->getName()));
                continue;
            }

            $repository->checkout($tag->getName());
            $created = new DateTimeImmutable($repository->log('-1', '--format=%ai', '--skip=1'));

            $this->logger->info(sprintf(
                'Moving tag %s from %s to %s',
                $tag->getName(),
                $tag->getCreated()->format('d-m-Y'),
                $created->format('d-m-Y')
            ));

            $tag->setCreated($created);
        }
    }
}
