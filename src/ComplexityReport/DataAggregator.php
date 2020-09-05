<?php

declare(strict_types=1);

namespace App\ComplexityReport;

use App\ComplexityReport\Git\Tag;
use App\Entity\Library;
use App\Entity\Project;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class DataAggregator
{
    private ProjectRepository $projectRepository;
    private GitController $gitController;
    private CodeAnalyser $codeAnalyser;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;

    public function __construct(
        ProjectRepository $projectRepository,
        GitController $gitController,
        CodeAnalyser $codeAnalyser,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger
    ) {
        $this->projectRepository = $projectRepository;
        $this->gitController = $gitController;
        $this->codeAnalyser = $codeAnalyser;
        $this->logger = $logger;
        $this->entityManager = $entityManager;
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

                $this->gitController->checkoutTag($library, $tag->getName());
                $this->collectTagData($library, $tag);
            }
        }
    }

    private function collectTagData(Library $library, Tag $tag): void
    {
        $analysis = $this->codeAnalyser->analyse($library);
        $library->addTag($tag, $analysis);
    }
}
