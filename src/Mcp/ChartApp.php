<?php

declare(strict_types=1);

namespace App\Mcp;

use App\ComplexityReport\Metric\MetricCatalog;
use App\ComplexityReport\Metric\MetricSelection;
use App\Repository\RepositoryRepository;
use Mcp\Exception\ToolCallException;
use Symfony\AI\McpBundle\Attribute\AsMcpApp;
use Symfony\AI\McpBundle\Attribute\AsMcpAppTool;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsMcpApp(
    uri: 'ui://complexity-chart',
    name: 'complexity_chart',
    title: 'Complexity Chart',
    description: 'Draws how the complexity (or any other measured metric) of open source PHP repositories evolved over their releases, as an interactive line chart. Takes comma-separated repository slugs (e.g. "symfony/console,laravel/framework") and metric slugs (e.g. "complexity,loc"); with no repositories given it opens on the most starred ones.',
    template: 'mcp/chart/app.html.twig',
)]
final readonly class ChartApp
{
    /**
     * Where a chart starts when nobody picked anything - the same eight lines the chart page opens on.
     */
    private const int DEFAULT_LINES = 8;

    public function __construct(
        private RepositoryRepository $repositories,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @param string $repositories Comma-separated repository slugs as they appear on github.com, e.g. "symfony/console,laravel/framework". Unknown slugs are dropped; empty means the most starred repositories of the report.
     * @param string $metrics      Comma-separated metric slugs the chart can be read in, e.g. "complexity,loc". Unknown slugs are dropped; empty means complexity, which is what the report is about.
     *
     * @return array<string, mixed>
     */
    public function render(string $repositories = '', string $metrics = ''): array
    {
        $selection = MetricSelection::fromInput($metrics);

        $analysed = $this->repositories->findAnalysed();
        $picked = '' === trim($repositories)
            ? \array_slice($analysed, 0, self::DEFAULT_LINES)
            : $this->repositories->findBySlugs(self::slugs($repositories));

        $lines = array_values(array_filter($picked, static fn ($repository) => $repository->hasData()));

        return [
            'series' => array_map(
                static fn ($repository) => $repository->asGraph($selection->getPicked())->jsonSerialize(),
                $lines,
            ),
            'metrics' => $selection->getSlugs(),
            'catalog' => (new MetricCatalog())->jsonSerialize(),
            // the chart page, which the app links to with what it draws as the query string
            'website' => $this->urlGenerator->generate('chart', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ];
    }

    /**
     * @param string $query what was typed into the picker - matched against the repository slugs the report carries
     *
     * @return array{repositories: list<string>}
     */
    #[AsMcpAppTool(
        name: 'chart_repository_search',
        title: 'Chart Repository Search',
        description: 'Repository suggestions for the picker of the chart app: slugs of measured repositories matching the query, most starred first.',
        appOnly: true,
    )]
    public function searchRepositories(string $query): array
    {
        return ['repositories' => array_values($this->repositories->search($query))];
    }

    /**
     * @param string $repository The slug of the repository the line is drawn for, e.g. "symfony/console".
     * @param string $metrics    comma-separated metric slugs the line has to carry - every metric of the chart, so switching a tab stays a redraw
     *
     * @return array<string, mixed>
     */
    #[AsMcpAppTool(
        name: 'chart_line',
        title: 'Chart Line',
        description: 'One line of the chart app: the releases of a repository with the values of the requested metrics, as drawn into the chart.',
        appOnly: true,
    )]
    public function line(string $repository, string $metrics = ''): array
    {
        $found = $this->repositories->findBySlug(trim($repository));

        if (null === $found || !$found->hasData()) {
            throw new ToolCallException(\sprintf('The report carries no measured repository "%s".', trim($repository)));
        }

        return $found->asGraph(MetricSelection::fromInput($metrics)->getPicked())->jsonSerialize();
    }

    /**
     * @return list<string>
     */
    private static function slugs(string $input): array
    {
        return array_values(array_filter(array_map(trim(...), explode(',', $input))));
    }
}
