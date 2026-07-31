<?php

declare(strict_types=1);

namespace App\ComplexityReport;

use App\Entity\Repository;
use App\Repository\RepositoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

final class DataAggregator
{
    public function __construct(
        private RepositoryRepository $repositoryRepository,
        private GitController $gitController,
        private CacheItemPoolInterface $cache,
        private CodeAnalyser $codeAnalyser,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return int number of repositories that were analysed successfully
     */
    public function aggregate(bool $pendingOnly = false): int
    {
        $repositories = $pendingOnly
            ? $this->repositoryRepository->findPending()
            : $this->repositoryRepository->findAllByStars();

        $analysed = 0;

        foreach ($repositories as $repository) {
            // a single broken repository must not stop the analysis of everything that was submitted with it
            try {
                $this->aggregateRepository($repository);
            } catch (\Throwable $exception) {
                $this->logger->error(sprintf(
                    'Cannot analyse repository %s: %s',
                    $repository->getName(),
                    $exception->getMessage()
                ));
                continue;
            }

            $repository->markAnalysed();
            $this->entityManager->flush();
            ++$analysed;
        }

        return $analysed;
    }

    private function aggregateRepository(Repository $repository): void
    {
        foreach ($this->getTags($repository) as $tag) {
            if ($tag->isPreRelease() || $tag->isPatchRelease()) {
                $this->logger->debug(sprintf('Skipping tag "%s"', $tag->getName()));
                continue;
            }

            if ($repository->hasTag($tag->getName())) {
                $this->logger->debug(sprintf('Tag "%s" is analysed already', $tag->getName()));
                continue;
            }

            $this->logger->info(sprintf('Collecting data for %s tag %s', $repository->getName(), $tag->getName()));
            $repository->addTag($tag, $this->collectTagData($repository, $tag));
        }
    }

    /**
     * @return GitTag[]
     */
    private function getTags(Repository $repository): array
    {
        $this->logger->info(sprintf('Loading tags for repository %s', $repository->getName()));
        $key = sprintf('%s_tags', str_replace('/', '_', $repository->getName()));
        $item = $this->cache->getItem($key);

        if (!$item->isHit()) {
            $tags = $this->gitController->loadTags($repository);

            $item->set($tags);
            $item->expiresAfter(3600);
            $this->cache->save($item);
        }

        return $item->get();
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
