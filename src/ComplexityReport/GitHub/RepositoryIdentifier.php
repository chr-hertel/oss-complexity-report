<?php

declare(strict_types=1);

namespace App\ComplexityReport\GitHub;

use App\ComplexityReport\Exception\SubmissionFailed;

/**
 * The `owner/name` pair identifying a repository on github.com.
 */
final class RepositoryIdentifier implements \Stringable
{
    private const string SEGMENT_PATTERN = '#^[A-Za-z0-9._-]+$#';

    /**
     * github.com caps an account at 39 and a repository at 100 characters.
     */
    private const int MAX_SEGMENT_LENGTH = 100;

    /**
     * Long enough for the deepest github.com url a repository can be pasted as, and short enough that
     * nothing downstream has to carry a payload - the input is quoted back in the rejection message.
     */
    private const int MAX_INPUT_LENGTH = 2048;

    /**
     * Segments that name a directory rather than a repository. A path is built from these (the working
     * copy) and so is a github.com api url, where `..` is resolved away before the request is sent - so
     * `owner/..` would ask for something else entirely.
     */
    private const array RESERVED_SEGMENTS = ['.', '..'];

    public function __construct(
        public readonly string $owner,
        public readonly string $name,
    ) {
        if (!self::isValidSegment($owner) || !self::isValidSegment($name)) {
            throw SubmissionFailed::invalidInput(sprintf('%s/%s', $owner, $name));
        }
    }

    /**
     * Accepts everything that identifies a repository, e.g. `symfony/console`,
     * `github.com/symfony/console` or `https://github.com/symfony/console.git`.
     */
    public static function fromInput(string $input): self
    {
        if (mb_strlen($input) > self::MAX_INPUT_LENGTH) {
            throw SubmissionFailed::invalidInput($input);
        }

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

    private static function isValidSegment(string $segment): bool
    {
        return 1 === preg_match(self::SEGMENT_PATTERN, $segment)
            && mb_strlen($segment) <= self::MAX_SEGMENT_LENGTH
            && !in_array($segment, self::RESERVED_SEGMENTS, true);
    }
}
