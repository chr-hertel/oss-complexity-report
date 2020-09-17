<?php

declare(strict_types=1);

namespace App\ComplexityReport\DataFixer;

use App\Repository\LibraryRepository;
use Doctrine\ORM\EntityManagerInterface;
use GitWrapper\GitWrapper;
use Psr\Log\LoggerInterface;

class LaminasMvcVersionFixer implements FixerInterface
{
    private LibraryRepository $repository;
    private GitWrapper $gitWrapper;
    private LoggerInterface $logger;
    private EntityManagerInterface $entityManager;
    private string $repositoryPath;

    public function __construct(
        LibraryRepository $repository,
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
        $laminasMvc = $this->repository->findOneByName('laminas/laminas-mvc');

        if (null === $laminasMvc) {
            throw new \RuntimeException('Cannot find laminas/laminas-mvc library.');
        }

        $repository = $this->gitWrapper->workingCopy($this->repositoryPath.'/'.$laminasMvc->getRepositoryPath());

        foreach ($laminasMvc->getTags() as $tag) {
            $repository->checkout($tag->getName());
            $created = new \DateTimeImmutable($repository->log('-1', '--format=%ai', '--skip=1'));

            $this->logger->info(sprintf(
                'Moving tag %s from %s to %s',
                $tag->getName(),
                $tag->getCreated()->format('d-m-Y'),
                $created->format('d-m-Y')
            ));

            $tag->setCreated($created);
        }

        $this->entityManager->flush();
    }
}
