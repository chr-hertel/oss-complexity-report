<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Library;
use App\Entity\Project;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ReportController extends AbstractController
{
    /**
     * @Route("", name="start", methods={"GET"})
     */
    public function start(): Response
    {
        return $this->render('start.html.twig');
    }

    /**
     * @Route("{vendor}", name="project", methods={"GET"}, priority=1)
     */
    public function project(Project $project): Response
    {
        return $this->render('project.html.twig', ['project' => $project]);
    }

    /**
     * @Route("{id}", name="library", methods={"GET"}, requirements={"id": "\d+"}, priority=2)
     */
    public function library(Library $library): JsonResponse
    {
        return new JsonResponse($library->asGraph()->getTagData());
    }
}
