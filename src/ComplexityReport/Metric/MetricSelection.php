<?php

declare(strict_types=1);

namespace App\ComplexityReport\Metric;

/**
 * What the chart can be read in: `?metrics=complexity,loc` - one tab per metric, in the order they were
 * asked for, and `?metric=loc` says which of them is the one being looked at.
 *
 * The rules are the ones the repositories of a chart already follow, because it is the same query
 * string and the same reader: a metric is named by the slug it is spoken about under, one is enough
 * however often it is repeated, and anything that is not a metric of the report is dropped rather than
 * answered with an error. What is left of an empty selection is the metric the report is about.
 *
 * `?metric=` is the switch the chart grew out of, and it still means what it meant: the number the
 * chart is drawn as. It only says less than it used to - which of the tabs is open, rather than that
 * there are two of them - so the links that were handed out under it still open what they named.
 */
final readonly class MetricSelection
{
    /**
     * @param list<Metric> $picked
     */
    private function __construct(private array $picked, private Metric $active)
    {
    }

    /**
     * @param string $metrics what `?metrics=` was given as
     * @param string $legacy  what `?metric=` was given as
     */
    public static function fromInput(string $metrics, string $legacy = ''): self
    {
        $picked = self::parse($metrics);
        $shown = self::parse($legacy);

        // a chart addressed only by the metric it is drawn as is a chart of that one metric
        if ([] === $picked) {
            $picked = $shown;
        }

        if ([] === $picked) {
            $picked = [Metric::default()];
        }

        // the tab that is open, which is the first one unless the address names another of them
        $active = array_values(array_filter($shown, static fn (Metric $metric) => \in_array($metric, $picked, true)));

        return new self($picked, $active[0] ?? $picked[0]);
    }

    public static function of(Metric ...$metrics): self
    {
        $picked = [] === $metrics ? [Metric::default()] : array_values($metrics);

        return new self($picked, $picked[0]);
    }

    /**
     * The metrics the chart can be read in, in the order it tabs them - all of them travel with the
     * page, because switching between two numbers of a release the browser already has is a redraw and
     * must not be a request.
     *
     * @return list<Metric>
     */
    public function getPicked(): array
    {
        return $this->picked;
    }

    /**
     * The one the chart opens on.
     */
    public function getActive(): Metric
    {
        return $this->active;
    }

    public function has(Metric $metric): bool
    {
        return \in_array($metric, $this->picked, true);
    }

    /**
     * Everything that can be drawn, what is drawn first. The picked ones lead in the order they are
     * stacked, because the select behind the box is the state and reports what is selected in the order
     * it stands in - the same reason the repositories of a chart lead their own list.
     *
     * @return list<Metric>
     */
    public function getOptions(): array
    {
        $rest = array_filter(Metric::cases(), fn (Metric $metric) => !$this->has($metric));

        return array_merge($this->picked, array_values($rest));
    }

    /**
     * What the chart is drawn as, for the query string it is shared as.
     *
     * @return list<string>
     */
    public function getSlugs(): array
    {
        return array_map(static fn (Metric $metric) => $metric->value, $this->picked);
    }

    /**
     * Whether this is the chart nobody said anything about - the report is about complexity, so that
     * chart carries no metric in its address at all.
     */
    public function isDefault(): bool
    {
        return [Metric::default()] === $this->picked;
    }

    /**
     * The tabs a chart carries are its address; which one is open is only written down when it is not
     * the one a reader would land on anyway.
     */
    public function isFirstActive(): bool
    {
        return $this->active === $this->picked[0];
    }

    /**
     * @return list<Metric>
     */
    private static function parse(string $input): array
    {
        $picked = [];

        foreach (explode(',', $input) as $slug) {
            $metric = Metric::tryFrom(mb_strtolower(trim($slug)));

            // one line per metric: a chart of complexity and complexity is a chart of complexity
            if (null !== $metric && !\in_array($metric, $picked, true)) {
                $picked[] = $metric;
            }
        }

        return $picked;
    }
}
