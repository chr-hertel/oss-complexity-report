<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Hand the next few repositories that are missing part of their measurement to a worker - dispatched
 * hourly, so the releases measured before the report kept the full phploc output fill in over time.
 */
final readonly class BackfillMetrics
{
}
