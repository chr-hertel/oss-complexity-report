<?php

declare(strict_types=1);

namespace App\ComplexityReport;

use App\Entity\Library;
use App\Entity\Project;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

class DataAggregator
{
    private ProjectRepository $projectRepository;
    private GitController $gitController;
    private CacheItemPoolInterface $cache;
    private CodeAnalyser $codeAnalyser;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;

    public function __construct(
        ProjectRepository $projectRepository,
        GitController $gitController,
        CacheItemPoolInterface $cache,
        CodeAnalyser $codeAnalyser,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger
    ) {
        $this->projectRepository = $projectRepository;
        $this->gitController = $gitController;
        $this->cache = $cache;
        $this->codeAnalyser = $codeAnalyser;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
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
            $tags = $this->gitController->loadTags($library);

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
