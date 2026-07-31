<?php

declare(strict_types=1);

namespace App\ComplexityReport;

use App\ComplexityReport\Exception\SubmissionFailed;
use App\ComplexityReport\GitHub\RepositoryIdentifier;
use App\Repository\RepositoryRepository;

/**
 * The one input on the start page does both jobs: it finds what the report already carries, and it takes
 * anything that identifies a repository on github.com so it can be submitted.
 *
 * Which of the two an input is stays a server decision - `RepositoryIdentifier` already knows every form
 * a repository may be pasted in, and the search box should not learn those rules a second time.
 */
final readonly class RepositorySearch
{
    /**
     * Below two characters every repository matches, which is not a suggestion.
     */
    private const int MIN_LENGTH = 2;

    public function __construct(private RepositoryRepository $repositories)
    {
    }

    public function search(string $input, int $limit): SearchResult
    {
        $query = trim($input);

        if (mb_strlen($query) < self::MIN_LENGTH) {
            return new SearchResult([]);
        }

        return new SearchResult(
            $this->repositories->findByNameLike($query, $limit),
            $this->submittable($query),
        );
    }

    /**
     * What the input identifies on github.com - unless the report already carries it, in which case the
     * match itself is the answer and there is nothing to submit.
     */
    private function submittable(string $query): ?RepositoryIdentifier
    {
        try {
            $identifier = RepositoryIdentifier::fromInput($query);
        } catch (SubmissionFailed) {
            // not something that names a repository - the matches above are all there is
            return null;
        }

        return null === $this->repositories->findOneBy(['name' => (string) $identifier]) ? $identifier : null;
    }
}
