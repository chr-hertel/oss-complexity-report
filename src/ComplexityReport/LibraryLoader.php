<?php

declare(strict_types=1);

namespace App\ComplexityReport;

use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;

class LibraryLoader
{
    private ProjectRepository $repository;
    private PackagistClient $client;
    private EntityManagerInterface $entityManager;

    public function __construct(ProjectRepository $repository, PackagistClient $client, EntityManagerInterface $entityManager)
    {
        $this->repository = $repository;
        $this->client = $client;
        $this->entityManager = $entityManager;
    }

    public function load(): void
    {
        $projects = $this->repository->findAll();

        foreach ($projects as $project) {
            $libraries = $this->client->fetchNewLibraries($project);
            array_map([$this->entityManager, 'persist'], $libraries);
        }

        $this->entityManager->flush();
    }
}
