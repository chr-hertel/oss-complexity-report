<?php

declare(strict_types=1);

namespace App\Controller;

use App\ComplexityReport\ChartSelection;
use App\ComplexityReport\Exception\SubmissionFailed;
use App\ComplexityReport\PhplocReport;
use App\ComplexityReport\Ranking;
use App\ComplexityReport\RankingLoader;
use App\ComplexityReport\RepositorySearch;
use App\ComplexityReport\RepositorySubmitter;
use App\ComplexityReport\StatisticsLoader;
use App\ComplexityReport\Trend\TrendLoader;
use App\Entity\Organization;
use App\Entity\Repository;
use App\Repository\OrganizationRepository;
use App\Repository\RepositoryRepository;
use App\Repository\TagRepository;
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
     * Number of lines the chart opens on when nobody picked any - the eight colours of the palette, so
     * the chart it starts with has one each. It is where a chart begins, not what it holds: a selection
     * says how many lines it draws, and the report is the only thing that bounds it.
     */
    private const int CHART_DEFAULT = 8;

    /**
     * Number of repositories featured on the start page.
     */
    private const int FEATURED_LIMIT = 9;

    /**
     * Number of suggestions the search box offers while someone types.
     */
    private const int SEARCH_LIMIT = 8;

    /**
     * Number of recently submitted repositories, and of recently measured releases, the start page
     * closes with - the two lists stand next to each other, so they are the same length.
     */
    private const int LATEST_LIMIT = 12;

    #[Route('', name: 'start', methods: 'GET')]
    public function start(
        RepositoryRepository $repositoryRepository,
        OrganizationRepository $organizationRepository,
        TagRepository $tagRepository,
        StatisticsLoader $statisticsLoader,
        TrendLoader $trendLoader,
        RankingLoader $rankingLoader,
    ): Response {
        // one read for every ranking - they all sort the same set, only by a different measurement
        $analysed = $rankingLoader->load();

        $rankings = array_map(static function (Ranking $ranking) use ($analysed) {
            return ['ranking' => $ranking, 'repositories' => $ranking->sort($analysed, self::FEATURED_LIMIT)];
        }, Ranking::all());

        return $this->render('start.html.twig', [
            'rankings' => $rankings,
            'hasData' => [] !== $analysed,
            'vendors' => $organizationRepository->findVendors(),
            'latest' => $repositoryRepository->findLatest(self::LATEST_LIMIT),
            'latestReleases' => $tagRepository->findLatest(self::LATEST_LIMIT),
            'statistics' => $statisticsLoader->load(),
            'trends' => $trendLoader->load(),
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
                'url' => $this->generateUrl('chart', ['repositories' => $repository->getName()]),
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

    /**
     * The one chart of the report: `?repositories=symfony/console,laravel/framework` says which lines it
     * draws, and anything the report carries can be added to them. Without a selection it opens on the
     * most starred repositories.
     *
     * How many lines that may be is not capped: a vendor with fifty measured repositories is a chart of
     * fifty. What bounds the query string is the report itself - it cannot name more repositories than
     * there are, and everything it names that is not one is dropped.
     */
    #[Route('chart', name: 'chart', methods: 'GET', priority: 3)]
    public function chart(Request $request, RepositoryRepository $repositories): Response
    {
        $analysed = $repositories->findAnalysed();
        $selected = $repositories->findBySlugs($this->selectedSlugs($request, $repositories->count([])));

        return $this->render('chart.html.twig', [
            'selection' => [] === $selected
                ? ChartSelection::mostStarred($analysed, self::CHART_DEFAULT)
                : ChartSelection::of($selected, $analysed),
        ]);
    }

    /**
     * A GitHub account used to have a page of its own; it is the chart above, opened with what was
     * submitted for that account - the links that were handed out keep working.
     */
    #[Route('{organization}', name: 'organization', methods: 'GET', priority: 1)]
    public function organization(
        #[MapEntity(mapping: ['organization' => 'login'])] Organization $organization,
        Request $request,
    ): Response {
        $repositories = $organization->getAnalysedRepositories() ?: $organization->getRepositories();

        // those links address a repository by the id it had when they were written
        $preselected = $request->query->getInt('repository');

        if (0 !== $preselected) {
            $repositories = array_filter(
                $repositories,
                static fn (Repository $repository) => $repository->getId() === $preselected,
            ) ?: $repositories;
        }

        $slugs = array_map(static fn (Repository $repository) => $repository->getName(), $repositories);

        return $this->redirectToRoute(
            'chart',
            ['repositories' => implode(',', $slugs)],
            Response::HTTP_MOVED_PERMANENTLY,
        );
    }

    /**
     * One line of the chart: the repository and the releases behind it, in the shape the page is
     * rendered with, so a line picked later reads the same as one the page opened on. A repository is
     * addressed the way github.com addresses it, and that slug is the whole route - so the select box
     * can request it relative to the page it is on, which keeps working under a deployed sub path.
     */
    #[Route('{name}', name: 'repository', requirements: ['name' => '[^/]+/[^/]+'], methods: 'GET', priority: 2)]
    public function repository(string $name, RepositoryRepository $repositories): JsonResponse
    {
        $repository = $repositories->findBySlug($name) ?? throw $this->createNotFoundException();

        return new JsonResponse($repository->asGraph());
    }

    /**
     * One release as phploc printed it - the sixty-odd numbers a measurement consists of, of which the
     * report itself plots two.
     *
     * It hangs under the release rather than under the repository, because that is what it belongs to,
     * and it is fetched when somebody opens it rather than rendered into the chart page: a release is
     * read one at a time, while a chart carries hundreds of them.
     *
     * A release measured before the report kept the full output has none, and says so with a 404 - the
     * panel does not offer it there, so this is the answer to a link that was kept or typed.
     */
    #[Route('{name}/{tag}/raw', name: 'raw', requirements: ['name' => '[^/]+/[^/]+', 'tag' => '.+'], methods: 'GET', priority: 2)]
    public function raw(
        string $name,
        string $tag,
        RepositoryRepository $repositories,
        TagRepository $tags,
        PhplocReport $report,
    ): Response {
        $repository = $repositories->findBySlug($name) ?? throw $this->createNotFoundException();
        $metrics = $tags->findOneBy(['repository' => $repository, 'name' => $tag])?->getMetrics()
            ?? throw $this->createNotFoundException();

        $response = new Response($report->render($metrics), Response::HTTP_OK, ['Content-Type' => 'text/plain; charset=UTF-8']);

        // what a released tag was measured as does not change again
        $response->setPublic()->setMaxAge(86400);

        return $response;
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
     * The page of a repository is the chart, drawn for that one repository.
     */
    private function toRepository(Repository $repository): Response
    {
        return $this->redirectToRoute(
            'chart',
            ['repositories' => $repository->getName()],
            Response::HTTP_SEE_OTHER,
        );
    }

    /**
     * What `?repositories=symfony/console,laravel/framework` asks for: slugs, in the order they were
     * given. Anything that is not a repository is dropped rather than answered with an error - the query
     * string is a link people edit and share.
     *
     * $limit is how many repositories the report carries. It is not a limit on the chart - a selection
     * cannot name more repositories than exist, so this only keeps a query string somebody padded out
     * from being asked about.
     *
     * @return list<string>
     */
    private function selectedSlugs(Request $request, int $limit): array
    {
        $slugs = array_filter(array_map(trim(...), explode(',', $request->query->getString('repositories'))));

        // `symfony/console` and `Symfony/Console` are the same repository, and one line of it is enough
        $unique = array_unique(array_map(mb_strtolower(...), $slugs));

        return \array_slice(array_values(array_intersect_key($slugs, $unique)), 0, $limit);
    }
}
