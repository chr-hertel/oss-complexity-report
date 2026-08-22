<?php

declare(strict_types=1);

namespace App\Mcp\CompletionProvider;

use App\Repository\RepositoryRepository;
use Mcp\Capability\Completion\ProviderInterface;

final readonly class RepositoryCompletionProvider implements ProviderInterface
{
    public function __construct(
        private RepositoryRepository $repository,
    ) {
    }

    /***
     * @return string[]
     */
    public function getCompletions(string $currentValue): array
    {
        return $this->repository->findNamesByStartsWith($currentValue);
    }
}
