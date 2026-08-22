<?php

declare(strict_types=1);

namespace App\Mcp\CompletionProvider;

use App\Repository\TagRepository;
use Mcp\Capability\Completion\ProviderInterface;

final readonly class TagCompletionProvider implements ProviderInterface
{
    public function __construct(
        private TagRepository $repository,
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
