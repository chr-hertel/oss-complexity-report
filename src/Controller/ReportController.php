<?php

declare(strict_types=1);

namespace App\Controller;

use App\ComplexityReport\Exception\SubmissionFailed;
use App\ComplexityReport\Ranking;
use App\ComplexityReport\RepositorySearch;
use App\ComplexityReport\RepositorySubmitter;
use App\ComplexityReport\StatisticsLoader;
use App\Entity\Organization;
use App\Entity\Repository;
use App\Repository\OrganizationRepository;
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

    /**
     * Number of suggestions the search box offers while someone types.
     */
    private const int SEARCH_LIMIT = 8;

    #[Route('', name: 'start', methods: 'GET')]
    public function start(
        RepositoryRepository $repositoryRepository,
        OrganizationRepository $organizationRepository,
        StatisticsLoader $statisticsLoader,
    ): Response {
        // one query for every ranking - they all sort the same set, only by a different measurement
        $analysed = $repositoryRepository->findAnalysedWithTags();

        $rankings = array_map(static function (Ranking $ranking) use ($analysed) {
            return ['ranking' => $ranking, 'repositories' => $ranking->sort($analysed, self::FEATURED_LIMIT)];
        }, Ranking::all());

        return $this->render('start.html.twig', [
            'rankings' => $rankings,
            'hasData' => [] !== $analysed,
            'organizations' => $organizationRepository->findWithData(),
            'pending' => $repositoryRepository->findPending(),
            'statistics' => $statisticsLoader->load(),
        ]);
    }

    /**
     * Suggestions for the search box: what the report carries, plus what the input could be submitted as.
     */
    #[Route('search', name: 'search', methods: 'GET', priority: 3)]
    public function search(Request $request, RepositorySearch $search): JsonResponse
    {
        $result = $search->search((string) $request->query->get('q', ''), self::SEARCH_LIMIT);

        return new JsonResponse([
            'repositories' => array_map(fn (Repository $repository) => [
                'name' => $repository->getName(),
                'description' => $repository->getDescription(),
                'stars' => $repository->getStars(),
                'analysed' => $repository->isAnalysed(),
                'url' => $this->generateUrl('organization', [
                    'organization' => $repository->getOrganization()->getLogin(),
                    'repository' => $repository->getId(),
                ]),
            ], $result->matches),
            'submittable' => null === $result->submittable ? null : ['name' => (string) $result->submittable],
        ]);
    }

    /**
     * Every answer a visitor gets is a redirect, and every one of them says 303: turbo follows the answer
     * to a submission itself, and only a See Other tells it to read the page that follows with a GET. The
     * two refusals below are the exception - see refuse().
     */
    #[Route('submit', name: 'submit', methods: 'POST', priority: 3)]
    public function submit(
        Request $request,
        RepositorySubmitter $submitter,
        RateLimiterFactoryInterface $submissionLimiter,
    ): Response {
        // every submission spends github.com API quota and ends in a clone, so it is worth a limit -
        // and it is spent before anything else, because the checks below are not free either
        $limit = $submissionLimiter->create($request->getClientIp())->consume();

        if (!$limit->isAccepted()) {
            return $this->refuse(
                'That is a lot of repositories at once - please try again in a few minutes.',
                Response::HTTP_TOO_MANY_REQUESTS,
                ['Retry-After' => max(0, $limit->getRetryAfter()->getTimestamp() - time())]
            );
        }

        if (!$this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            return $this->refuse('That form expired - please submit the repository again.', Response::HTTP_BAD_REQUEST);
        }

        try {
            $submission = $submitter->submit((string) $request->request->get('repository', ''));
        } catch (SubmissionFailed $exception) {
            $this->addFlash('danger', $exception->getMessage());

            return $this->redirectToRoute('start', status: Response::HTTP_SEE_OTHER);
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

    #[Route('{organization}', name: 'organization', methods: 'GET', priority: 1)]
    public function organization(
        #[MapEntity(mapping: ['organization' => 'login'])] Organization $organization,
        Request $request,
    ): Response {
        $selected = $this->selectRepository($organization, $request->query->getInt('repository'));

        return $this->render('chart.html.twig', [
            'headline' => sprintf('Organization: %s', $organization->getLogin()),
            'organization' => $organization,
            // a repository without releases has nothing to draw yet, the status below the headline says so
            'selectedRepositories' => $selected->hasData() ? [$selected] : [],
            'repositories' => $organization->getAnalysedRepositories(),
            'pendingRepository' => $selected->isAnalysed() ? null : $selected,
        ]);
    }

    #[Route('{id}', name: 'repository', requirements: ['id' => '\d+'], methods: 'GET', priority: 2)]
    public function repository(#[MapEntity(mapping: ['id' => 'id'])] Repository $repository): JsonResponse
    {
        return new JsonResponse($repository->asGraph()->getTagData());
    }

    /**
     * A refusal that never touches the session.
     *
     * Every other answer to a submission is a flash on the start page, which is what a human should read.
     * A flash starts a session though, and these two refusals are the ones a script runs into rather than
     * a visitor: a request over the limit, and one without a token. Answering those with a flash would
     * write a session per request, for as many requests as someone cares to send.
     *
     * @param array<string, int|string> $headers
     */
    private function refuse(string $message, int $status, array $headers = []): Response
    {
        return new Response($message, $status, $headers + ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /**
     * The page of a repository is the one of its organization, with the repository preselected.
     */
    private function toRepository(Repository $repository): Response
    {
        return $this->redirectToRoute('organization', [
            'organization' => $repository->getOrganization()->getLogin(),
            'repository' => $repository->getId(),
        ], Response::HTTP_SEE_OTHER);
    }

    /**
     * Every repository can be picked, analysed or not - one that was just submitted has a page, too.
     */
    private function selectRepository(Organization $organization, int $id): Repository
    {
        foreach ($organization->getRepositories() as $repository) {
            if ($repository->getId() === $id) {
                return $repository;
            }
        }

        return $organization->getMainRepository();
    }
}
