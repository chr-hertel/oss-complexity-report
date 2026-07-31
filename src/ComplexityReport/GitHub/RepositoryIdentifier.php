<?php

declare(strict_types=1);

namespace App\ComplexityReport\GitHub;

use App\ComplexityReport\Exception\SubmissionFailed;

/**
 * The `owner/name` pair identifying a repository on github.com.
 */
final class RepositoryIdentifier implements \Stringable
{
    private const SEGMENT_PATTERN = '#^[A-Za-z0-9._-]+$#';

    public function __construct(
        public readonly string $owner,
        public readonly string $name,
    ) {
        if (1 !== preg_match(self::SEGMENT_PATTERN, $owner) || 1 !== preg_match(self::SEGMENT_PATTERN, $name)) {
            throw SubmissionFailed::invalidInput(sprintf('%s/%s', $owner, $name));
        }
    }

    /**
     * Accepts everything that identifies a repository, e.g. `symfony/console`,
     * `github.com/symfony/console` or `https://github.com/symfony/console.git`.
     */
    public static function fromInput(string $input): self
    {
        $normalized = trim($input);
        $normalized = preg_replace('#^(https?://|ssh://|git@)#i', '', $normalized) ?? $normalized;
        $normalized = str_replace('github.com:', 'github.com/', $normalized);
        $normalized = preg_replace('#\.git$#i', '', $normalized) ?? $normalized;

        $segments = explode('/', trim($normalized, '/'));

        // vendors don't contain dots, so a first segment that does is a host - and github.com is the only one we read
        if (str_contains($segments[0], '.')) {
            $host = mb_strtolower((string) array_shift($segments));

            if (!in_array($host, ['github.com', 'www.github.com'], true)) {
                throw SubmissionFailed::invalidInput($input);
            }
        }

        if (count($segments) < 2 || '' === $segments[0] || '' === $segments[1]) {
            throw SubmissionFailed::invalidInput($input);
        }

        return new self($segments[0], $segments[1]);
    }

    public function __toString(): string
    {
        return sprintf('%s/%s', $this->owner, $this->name);
    }
}
