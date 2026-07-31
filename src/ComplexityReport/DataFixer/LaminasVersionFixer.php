<?php

declare(strict_types=1);

namespace App\ComplexityReport\DataFixer;

use App\ComplexityReport\GitController;
use App\Entity\Repository;
use App\Repository\OrganizationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Fixing tags that where originally Zend Framework releases.
 */
final class LaminasVersionFixer implements FixerInterface
{
    public function __construct(
        private OrganizationRepository $repository,
        private GitController $gitController,
        private LoggerInterface $logger,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function fixData(): void
    {
        // repositories are submitted by users now, so laminas is not necessarily part of the report
        $laminas = $this->repository->findOneByLogin('laminas');

        if (null === $laminas) {
            $this->logger->info('No laminas repositories submitted, nothing to fix.');

            return;
        }

        foreach ($laminas->getRepositories() as $repository) {
            $this->logger->info(sprintf('Checking %s for misplaced releases.', $repository->getName()));
            $this->shiftReleases($repository);
        }

        $this->entityManager->flush();
    }

    private function shiftReleases(Repository $repository): void
    {
        if (!$this->gitController->isCloned($repository)) {
            $this->logger->info(sprintf('Repository %s is not cloned yet, skipping.', $repository->getName()));

            return;
        }

        foreach ($repository->getTags() as $tag) {
            if ('2019-12-31' !== $tag->getCreated()->format('Y-m-d')) {
                $this->logger->info(sprintf('Skipping release %s, looks good.', $tag->getName()));
                continue;
            }

            $this->gitController->checkoutTag($repository, $tag->getName());
            $created = $this->gitController->getLastCommitDate($repository, 1);

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
