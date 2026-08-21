<?php

declare(strict_types=1);

namespace App\Mcp;

use App\ComplexityReport\Metric\Metric;
use App\Mcp\CompletionProvider\RepositoryCompletionProvider;
use App\Mcp\CompletionProvider\TagCompletionProvider;
use App\Repository\RepositoryRepository;
use App\Repository\TagRepository;
use Mcp\Capability\Attribute\CompletionProvider;
use Mcp\Capability\Attribute\McpResource;
use Mcp\Capability\Attribute\McpResourceTemplate;
use Mcp\Exception\ResourceNotFoundException;

final readonly class Metrics
{
    public function __construct(
        private RepositoryRepository $repositoryRepository,
        private TagRepository $tagRepository,
    ) {
    }

    /**
     * @return array<string, string>
     */
    #[McpResource(
        uri: 'metrics://catalog',
        name: 'metrics_catalog',
        title: 'Metrics Catalog',
        description: 'Retrieves a catalog of available metrics for repositories and tags',
        mimeType: 'application/json',
    )]
    public function getMetricsCatalog(): array
    {
        $keys = Metric::cases();

        $describedMetrics = [];
        foreach ($keys as $key) {
            $describedMetrics[$key->value] = $key->about();
        }

        return $describedMetrics;
    }

    /**
     * Retrieves metrics for a specific repository based on organization/user and repository, e.g. metrics://symfony/console.
     *
     * @return array<string, array<string, int|float>>
     */
    #[McpResourceTemplate(
        uriTemplate: 'metrics://{repository}',
        name: 'repository_metrics',
        title: 'Repository Metrics',
        description: 'Retrieves metrics for a specific repository based on organization/user and repository, e.g. metrics://symfony/console',
        mimeType: 'application/json',
    )]
    public function getRepositoryMetrics(
        #[CompletionProvider(provider: RepositoryCompletionProvider::class)]
        string $repository,
    ): array {
        $repository = $this->repositoryRepository->findOneByName(urldecode($repository));

        if (null === $repository) {
            throw new ResourceNotFoundException(sprintf('Metrics for %s not found', $repository));
        }

        return $repository->getMetrics();
    }

    /**
     * Retrieves metrics for a specific repository based on organization/user, repository, and version tag,e.g. metrics://symfony/console/8.1.0.
     *
     * @return array<string, int|float>
     */
    #[McpResourceTemplate(
        uriTemplate: 'metrics://{repository}/{tag}',
        name: 'repository_tag_metrics',
        title: 'Repository Tag Metrics',
        description: 'Retrieves metrics for a specific repository based repository path, and version tag, e.g. metrics://symfony/console/8.1.0',
        mimeType: 'application/json',
    )]
    public function getRepositoryMetricsForTag(
        #[CompletionProvider(provider: RepositoryCompletionProvider::class)]
        string $repository,
        #[CompletionProvider(provider: TagCompletionProvider::class)]
        string $tag,
    ): array {
        $tag = $this->tagRepository->findByRepositoryAndTag(urldecode($repository), urldecode($tag));

        if (null === $tag) {
            throw new ResourceNotFoundException(sprintf('Metrics for %s:%s not found', $repository, $tag));
        }

        return $tag->getMetrics();
    }
}
