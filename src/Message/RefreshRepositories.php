<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Re-read stars and metadata from github.com - dispatched nightly, since the report is ordered by them.
 */
final readonly class RefreshRepositories
{
}
