<?php

declare(strict_types=1);

namespace App\Controller;

use App\ComplexityReport\Exception\SubmissionFailed;
use App\ComplexityReport\RepositorySubmitter;
use App\ComplexityReport\StatisticsLoader;
use App\Entity\Project;
use App\Entity\Repository;
use App\Repository\ProjectRepository;
use App\Repository\RepositoryRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ReportController extends AbstractController
{
    /**
     * Number of repositories a chart starts with - it has eight colors to distinguish them.
     */
    private const int CHART_LIMIT = 8;

    /**
     * Number of repositories featured on the start page.
     */
    private const int FEATURED_LIMIT = 9;

    #[Route('', name: 'start', methods: 'GET')]
    public function start(
        RepositoryRepository $repositoryRepository,
        ProjectRepository $projectRepository,
        StatisticsLoader $statisticsLoader,
    ): Response {
        return $this->render('start.html.twig', [
            'featured' => $repositoryRepository->findMostStarred(self::FEATURED_LIMIT),
            'projects' => $projectRepository->findWithData(),
            'pending' => $repositoryRepository->findPending(),
            'statistics' => $statisticsLoader->load(),
        ]);
    }

    #[Route('submit', name: 'submit', methods: 'POST', priority: 3)]
    public function submit(Request $request, RepositorySubmitter $submitter): Response
    {
        try {
            $repository = $submitter->submit((string) $request->request->get('repository', ''));
        } catch (SubmissionFailed $exception) {
            $this->addFlash('danger', $exception->getMessage());

            return $this->redirectToRoute('start');
        }

        $this->addFlash('success', sprintf(
            'Thanks! %s is queued and will show up here as soon as its releases are analysed.',
            $repository->getName()
        ));

        return $this->redirectToRoute('start');
    }

    #[Route('overview', name: 'overview', methods: 'GET', priority: 3)]
    public function overview(RepositoryRepository $repository): Response
    {
        return $this->render('chart.html.twig', [
            'headline' => 'Most starred repositories',
            'selectedRepositories' => $repository->findMostStarred(self::CHART_LIMIT),
            'repositories' => $repository->findAnalysed(),
        ]);
    }

    #[Route('{vendor}', name: 'project', methods: 'GET', priority: 1)]
    public function project(
        #[MapEntity(mapping: ['vendor' => 'vendor'])] Project $project,
        Request $request,
    ): Response {
        $repositories = $project->getAnalysedRepositories();

        if ([] === $repositories) {
            throw $this->createNotFoundException(sprintf('None of the repositories of %s is analysed yet.', $project->getVendor()));
        }

        return $this->render('chart.html.twig', [
            'headline' => sprintf('Project: %s', $project->getName()),
            'project' => $project,
            'selectedRepositories' => [$this->selectRepository($project, $request->query->getInt('repository'))],
            'repositories' => $repositories,
        ]);
    }

    #[Route('{id}', name: 'repository', requirements: ['id' => '\d+'], methods: 'GET', priority: 2)]
    public function repository(#[MapEntity(mapping: ['id' => 'id'])] Repository $repository): JsonResponse
    {
        return new JsonResponse($repository->asGraph()->getTagData());
    }

    private function selectRepository(Project $project, int $id): Repository
    {
        foreach ($project->getAnalysedRepositories() as $repository) {
            if ($repository->getId() === $id) {
                return $repository;
            }
        }

        return $project->getMainRepository();
    }
}
