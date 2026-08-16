<?php

declare(strict_types=1);

namespace App\ComplexityReport\Metric;

/**
 * Whether a change in a metric is a statement or just a change.
 *
 * The report has an opinion about complexity: a codebase whose average complexity falls got easier to
 * test, and one whose average rose got harder. It has none about size - a library that grew by twenty
 * thousand lines did not thereby get better or worse, it got bigger, and saying otherwise would make
 * every release of every growing project look like a regression.
 *
 * So only the metrics that carry a risk are coloured; everything else is shown as the signed number it
 * is.
 */
enum MetricDirection: string
{
    /**
     * A change worth no colour - more classes are not better classes.
     */
    case Neutral = 'neutral';

    /**
     * Falling is the improvement: the complexities, where less branching is less risk.
     */
    case Lower = 'lower';
}
