<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Library;
use App\Entity\Project;
use App\Repository\ProjectRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ReportController extends AbstractController
{
    #[Route('', name: 'start', methods: 'GET')]
    public function start(ProjectRepository $projectRepository): Response
    {
        return $this->render('start.html.twig', [
            'projects' => $projectRepository->findAll(),
        ]);
    }

    #[Route('{vendor}', name: 'project', methods: 'GET', priority: 1)]
    public function project(Project $project, ProjectRepository $projectRepository): Response
    {
        return $this->render('project.html.twig', [
            'project' => $project,
            'projects' => $projectRepository->findAll(),
        ]);
    }

    #[Route('{id}', name: 'library', requirements: ['id' => '\d+'], methods: 'GET', priority: 2)]
    public function library(Library $library): JsonResponse
    {
        return new JsonResponse($library->asGraph()->getTagData());
    }
}
