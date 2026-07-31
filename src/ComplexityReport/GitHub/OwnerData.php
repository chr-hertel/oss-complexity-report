<?php

declare(strict_types=1);

namespace App\ComplexityReport\GitHub;

/**
 * The account a repository belongs to, as far as github.com states it.
 *
 * Deliberately down to the login and the avatar: the display name and the homepage an account may carry
 * are optional profile fields, and passing them off as the name of an organization was wrong as often as
 * it was right - `league` is a stranger to `thephpleague`, `phpunit` to `sebastianbergmann`.
 */
final class OwnerData
{
    public function __construct(
        public readonly string $login,
        public readonly ?string $avatarUrl,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromApiResponse(array $data): self
    {
        return new self(
            (string) $data['login'],
            isset($data['avatar_url']) ? (string) $data['avatar_url'] : null,
        );
    }
}
