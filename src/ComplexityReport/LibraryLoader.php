<?php

declare(strict_types=1);

namespace App\ComplexityReport;

use App\ComplexityReport\Packagist\Client;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;

class LibraryLoader
{
    private ProjectRepository $repository;
    private Client $client;
    private EntityManagerInterface $entityManager;

    public function __construct(ProjectRepository $repository, Client $client, EntityManagerInterface $entityManager)
    {
        $this->repository = $repository;
        $this->client = $client;
        $this->entityManager = $entityManager;
    }

    public function load(): void
    {
        $projects = $this->repository->findAll();

        foreach ($projects as $project) {
            $libraries = $this->client->fetchLibraries($project);
            array_map([$this->entityManager, 'persist'], $libraries);
        }

        $this->entityManager->flush();
    }
}
