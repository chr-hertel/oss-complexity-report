<?php

declare(strict_types=1);

namespace App\ComplexityReport;

use App\ComplexityReport\GitHub\RepositoryIdentifier;
use App\Entity\Repository;

/**
 * What the search box offers for one input: the repositories the report already carries, and - when the
 * input names a repository that is not one of them - the identifier it could be submitted under.
 */
final readonly class SearchResult
{
    /**
     * @param list<Repository> $matches
     */
    public function __construct(
        public array $matches,
        public ?RepositoryIdentifier $submittable = null,
    ) {
    }
}
