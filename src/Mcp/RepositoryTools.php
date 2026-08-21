<?php

declare(strict_types=1);

namespace App\Mcp;

use App\ComplexityReport\Exception\SubmissionFailed;
use App\ComplexityReport\RepositorySubmitter;
use App\Repository\RepositoryRepository;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Exception\ToolCallException;

final readonly class RepositoryTools
{
    public function __construct(
        private RepositoryRepository $repository,
        private RepositorySubmitter $submitter,
    ) {
    }

    /**
     * @param string $searchTerm the search term to look for in repository names, case does not matter
     *
     * @return string[]
     */
    #[McpTool(
        name: 'repository_search',
        title: 'Repository Search',
        description: 'Search for repositories the report carries by name, case-insensitive. Returns matching "owner/repository" slugs, most starred first.',
    )]
    public function search(string $searchTerm): array
    {
        return $this->repository->search($searchTerm);
    }

    /**
     * @param string $repositoryName Path to the GitHub repository, e.g. "symfony/console".
     */
    #[McpTool(
        name: 'repository_submit',
        title: 'Repository Submit',
        description: 'Submit a GitHub repository for analysis.',
    )]
    public function submit(string $repositoryName): string
    {
        try {
            $result = $this->submitter->submit($repositoryName);
        } catch (SubmissionFailed $e) {
            throw new ToolCallException(sprintf('Failed to submit repository "%s": %s', $repositoryName, $e->getMessage()));
        }

        if (false === $result->queued) {
            return sprintf('Repository "%s" is already part of the report (analyzed or queued for analysis).', $result->repository->getName());
        }

        return sprintf('Repository "%s" has been successfully queued for analysis.', $result->repository->getName());
    }
}
