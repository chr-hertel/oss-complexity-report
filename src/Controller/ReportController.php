<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Library;
use App\Entity\Project;
use App\Repository\LibraryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class ReportController extends AbstractController
{
    #[Route('', name: 'start', methods: 'GET')]
    public function start(): Response
    {
        return $this->render('start.html.twig');
    }

    #[Route('overview', name: 'overview', methods: 'GET', priority: 3)]
    public function overview(LibraryRepository $repository): Response
    {
        return $this->render('chart.html.twig', [
            'headline' => 'Overview',
            'selectedLibraries' => $repository->findSelected(),
            'libraries' => $repository->findAll(),
        ]);
    }

    #[Route('{vendor}', name: 'project', methods: 'GET', priority: 1)]
    public function project(Project $project): Response
    {
        return $this->render('chart.html.twig', [
            'headline' => sprintf('Project: %s', $project->getName()),
            'project' => $project,
            'selectedLibraries' => [$project->getMainLibrary()],
            'libraries' => $project->getLibraries(),
        ]);
    }

    #[Route('{id}', name: 'library', requirements: ['id' => '\d+'], methods: 'GET', priority: 2)]
    public function library(Library $library): JsonResponse
    {
        return new JsonResponse($library->asGraph()->getTagData());
    }
}
