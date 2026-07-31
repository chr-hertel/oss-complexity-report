<?php

declare(strict_types=1);

namespace App\ComplexityReport\GitHub;

final class OwnerData
{
    public function __construct(
        public readonly string $login,
        public readonly string $name,
        public readonly string $url,
        public readonly ?string $avatarUrl,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromApiResponse(array $data): self
    {
        $login = (string) $data['login'];
        $name = isset($data['name']) ? (string) $data['name'] : $login;
        $blog = isset($data['blog']) ? (string) $data['blog'] : '';

        return new self(
            $login,
            $name,
            '' !== $blog ? self::normalizeUrl($blog) : (string) $data['html_url'],
            isset($data['avatar_url']) ? (string) $data['avatar_url'] : null,
        );
    }

    private static function normalizeUrl(string $url): string
    {
        return str_starts_with($url, 'http') ? $url : 'https://'.$url;
    }
}
