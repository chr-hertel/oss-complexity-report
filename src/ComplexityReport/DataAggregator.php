<?php

declare(strict_types=1);

namespace App\ComplexityReport;

use App\Entity\Library;
use App\Entity\Project;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

final class DataAggregator
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private GitController $gitController,
        private CacheItemPoolInterface $cache,
        private CodeAnalyser $codeAnalyser,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    public function aggregate(): void
    {
        $projects = $this->projectRepository->findAll();

        foreach ($projects as $project) {
            $this->aggregateProject($project);
        }

        $this->entityManager->flush();
    }

    private function aggregateProject(Project $project): void
    {
        foreach ($project->getLibraries() as $library) {
            $tags = $this->getTags($library);

            foreach ($tags as $tag) {
                if ($tag->isPreRelease() || $tag->isPatchRelease()) {
                    $this->logger->debug(sprintf('Skipping tag "%s"', $tag->getName()));
                    continue;
                }

                $this->logger->info(sprintf('Collecting data for %s tag %s', $library->getName(), $tag->getName()));
                $analysis = $this->collectTagData($library, $tag);
                $library->addTag($tag, $analysis);
            }
        }
    }

    /**
     * @return GitTag[]
     */
    private function getTags(Library $library): array
    {
        $this->logger->info(sprintf('Loading tags for library %s', $library->getName()));
        $key = sprintf('%s_tags', str_replace('/', '_', $library->getName()));
        $item = $this->cache->getItem($key);

        if (!$item->isHit()) {
            $tags = $this->gitController->loadTags($library);

            $item->set($tags);
            $item->expiresAfter(3600);
            $this->cache->save($item);
        }

        return $item->get();
    }

    private function collectTagData(Library $library, GitTag $tag): Analysis
    {
        $key = sprintf('%s_%s_analysis', str_replace('/', '_', $library->getName()), $tag->getName());
        $item = $this->cache->getItem($key);

        if (!$item->isHit()) {
            $this->gitController->checkoutTag($library, $tag->getName());
            $analysis = $this->codeAnalyser->analyse($library);

            $item->set($analysis);
            $this->cache->save($item);
        }

        return $item->get();
    }
}
