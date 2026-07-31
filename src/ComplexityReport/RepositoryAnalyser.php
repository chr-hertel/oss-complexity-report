<?php

declare(strict_types=1);

namespace App\ComplexityReport;

use App\Entity\Repository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

/**
 * Measures every release of a single repository that is not measured yet.
 *
 * Checking out a tag rewrites the working copy, so only one analysis per repository may run at a time -
 * the caller is responsible for that (see AnalyseRepositoryHandler).
 */
final readonly class RepositoryAnalyser
{
    public function __construct(
        private ReleaseScanner $releaseScanner,
        private GitController $gitController,
        private CodeAnalyser $codeAnalyser,
        private CacheItemPoolInterface $cache,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return int number of releases that were measured
     */
    public function analyse(Repository $repository): int
    {
        $measured = 0;

        foreach ($this->releaseScanner->scanWorkingCopy($repository) as $tag) {
            $this->logger->info(sprintf('Collecting data for %s tag %s', $repository->getName(), $tag->getName()));

            $repository->addTag($tag, $this->collectTagData($repository, $tag));

            // flushed per release so a tag that cannot be analysed only costs its own work on the next try
            $this->entityManager->flush();
            ++$measured;
        }

        $repository->markAnalysed();
        $this->entityManager->flush();

        return $measured;
    }

    private function collectTagData(Repository $repository, GitTag $tag): Analysis
    {
        $key = sprintf('%s_%s_analysis', str_replace('/', '_', $repository->getName()), $tag->getName());
        $item = $this->cache->getItem($key);

        if (!$item->isHit()) {
            $this->gitController->checkoutTag($repository, $tag->getName());
            $analysis = $this->codeAnalyser->analyse($repository);

            $item->set($analysis);
            $this->cache->save($item);
        }

        return $item->get();
    }
}
