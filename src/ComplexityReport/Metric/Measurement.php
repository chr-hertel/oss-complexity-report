<?php

declare(strict_types=1);

namespace App\ComplexityReport\Metric;

/**
 * A stored phploc measurement, read through the catalog.
 *
 * The array a release carries is what phploc returned, keyed the way phploc keys it. This is the one
 * place that knows how to get a number out of it: what a metric is stored under, how far it is rounded,
 * and what share of its whole it is - the percentages phploc prints behind half of its numbers are not
 * stored anywhere either, they are the part over the whole, computed when the measurement is printed.
 *
 * Nothing here is a query. A measurement is read out of a release the report already loaded, so this is
 * arithmetic over an array, and it is where the arithmetic of the report belongs: the browser draws
 * what it is given.
 */
final readonly class Measurement
{
    /**
     * @param array<string, float|int> $metrics as measured, see {@see \App\ComplexityReport\Analysis::$metrics}
     */
    public function __construct(private array $metrics)
    {
    }

    /**
     * The number, rounded to what the metric is written with - null when the measurement does not carry
     * it, which is what a release measured by a phploc that counted something else would look like.
     */
    public function value(Metric $metric): int|float|null
    {
        $value = $this->metrics[$metric->source()] ?? null;

        if (!is_numeric($value)) {
            return null;
        }

        $decimals = $metric->decimals();

        return 0 === $decimals ? (int) round((float) $value) : round((float) $value, $decimals);
    }

    /**
     * What percentage of its whole this number is - `Static method calls` against all method calls -
     * and null for a number that is not part of anything, or whose whole is zero.
     */
    public function share(Metric $metric): ?float
    {
        $whole = $metric->partOf();

        if (null === $whole) {
            return null;
        }

        $total = $this->value($whole);
        $part = $this->value($metric);

        if (null === $total || null === $part || 0.0 === (float) $total) {
            return null;
        }

        return round(((float) $part / (float) $total) * 100, 2);
    }

    /**
     * The values of the metrics asked for, keyed by slug - what a line of the chart is drawn from.
     *
     * @param list<Metric> $metrics
     *
     * @return array<string, float|int|null>
     */
    public function values(array $metrics): array
    {
        $values = [];

        foreach ($metrics as $metric) {
            $values[$metric->value] = $this->value($metric);
        }

        return $values;
    }

    /**
     * The whole measurement interpreted: every number of the catalog with its share of its whole. This
     * is what the release panel reads - sixty numbers for one release, which is why it is fetched for
     * the release somebody opened rather than carried by a chart of hundreds of them.
     *
     * @return array{values: array<string, float|int|null>, shares: array<string, float>}
     */
    public function interpreted(): array
    {
        $shares = [];

        foreach (Metric::cases() as $metric) {
            $share = $this->share($metric);

            if (null !== $share) {
                $shares[$metric->value] = $share;
            }
        }

        return ['values' => $this->values(Metric::cases()), 'shares' => $shares];
    }
}
