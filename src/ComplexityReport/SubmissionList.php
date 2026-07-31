<?php

declare(strict_types=1);

namespace App\ComplexityReport;

/**
 * A list of repositories handed to the submitter in one go, as an operator writes it down.
 *
 * Submitting hundreds of repositories is a file, not a command line, so the format is the one such a file
 * ends up having anyway: one repository per line, blank lines to group them, `#` to say why one is there.
 * What a line holds is not parsed here - that is `RepositoryIdentifier`'s job, and it is the submitter that
 * gets to reject it.
 */
final readonly class SubmissionList
{
    /**
     * @param list<string> $repositories
     */
    private function __construct(public array $repositories)
    {
    }

    public static function fromText(string $text): self
    {
        $repositories = [];
        $seen = [];

        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            $line = trim(self::withoutComment($line));

            if ('' === $line) {
                continue;
            }

            // the same repository twice is a typo in a hand written list, not a second submission
            $key = mb_strtolower($line);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $repositories[] = $line;
        }

        return new self($repositories);
    }

    public function isEmpty(): bool
    {
        return [] === $this->repositories;
    }

    public function count(): int
    {
        return count($this->repositories);
    }

    private static function withoutComment(string $line): string
    {
        $comment = strpos($line, '#');

        return false === $comment ? $line : substr($line, 0, $comment);
    }
}
