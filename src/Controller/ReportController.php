<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Project;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
     * @Route("{vendor}", name="project", methods={"GET"})
     */
    public function project(Project $project): Response
    {
        return $this->render('project.html.twig', ['project' => $project]);
    }
}
