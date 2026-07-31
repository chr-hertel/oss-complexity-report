<?php

declare(strict_types=1);

namespace App\ComplexityReport\GitHub;

final class RepositoryData
{
    public function __construct(
        public readonly RepositoryIdentifier $identifier,
        public readonly string $url,
        public readonly string $cloneUrl,
        public readonly ?string $description,
        public readonly int $stars,
        public readonly bool $fork,
        public readonly bool $empty,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromApiResponse(array $data): self
    {
        /** @var array{login: string} $owner */
        $owner = $data['owner'];
        $identifier = new RepositoryIdentifier($owner['login'], (string) $data['name']);

        return new self(
            $identifier,
            (string) $data['html_url'],
            (string) $data['clone_url'],
            null !== $data['description'] ? (string) $data['description'] : null,
            (int) $data['stargazers_count'],
            (bool) $data['fork'],
            0 === (int) $data['size'],
        );
    }
}
