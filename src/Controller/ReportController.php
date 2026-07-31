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
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
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
    public function submit(
        Request $request,
        RepositorySubmitter $submitter,
        RateLimiterFactoryInterface $submissionLimiter,
    ): Response {
        if (!$this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'That form expired - please submit the repository again.');

            return $this->redirectToRoute('start');
        }

        // every submission spends github.com API quota and ends in a clone, so it is worth a limit
        if (!$submissionLimiter->create($request->getClientIp())->consume()->isAccepted()) {
            $this->addFlash('danger', 'That is a lot of repositories at once - please try again in a few minutes.');

            return $this->redirectToRoute('start');
        }

        try {
            $submission = $submitter->submit((string) $request->request->get('repository', ''));
        } catch (SubmissionFailed $exception) {
            $this->addFlash('danger', $exception->getMessage());

            return $this->redirectToRoute('start');
        }

        // a submission always ends on the page of its repository, which says what is happening to it -
        // a known one is simply already there
        return $this->toRepository($submission->repository);
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
        $selected = $this->selectRepository($project, $request->query->getInt('repository'));

        return $this->render('chart.html.twig', [
            'headline' => sprintf('Project: %s', $project->getName()),
            'project' => $project,
            // a repository without releases has nothing to draw yet, the status below the headline says so
            'selectedRepositories' => $selected->hasData() ? [$selected] : [],
            'repositories' => $project->getAnalysedRepositories(),
            'pendingRepository' => $selected->isAnalysed() ? null : $selected,
        ]);
    }

    #[Route('{id}', name: 'repository', requirements: ['id' => '\d+'], methods: 'GET', priority: 2)]
    public function repository(#[MapEntity(mapping: ['id' => 'id'])] Repository $repository): JsonResponse
    {
        return new JsonResponse($repository->asGraph()->getTagData());
    }

    /**
     * The page of a repository is the one of its project, with the repository preselected.
     */
    private function toRepository(Repository $repository): Response
    {
        return $this->redirectToRoute('project', [
            'vendor' => $repository->getProject()->getVendor(),
            'repository' => $repository->getId(),
        ]);
    }

    /**
     * Every repository can be picked, analysed or not - one that was just submitted has a page, too.
     */
    private function selectRepository(Project $project, int $id): Repository
    {
        foreach ($project->getRepositories() as $repository) {
            if ($repository->getId() === $id) {
                return $repository;
            }
        }

        return $project->getMainRepository();
    }
}
