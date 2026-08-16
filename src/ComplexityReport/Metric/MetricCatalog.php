<?php

declare(strict_types=1);

namespace App\ComplexityReport\Metric;

/**
 * The catalog, handed to the browser.
 *
 * The chart names metrics, formats their numbers, decides which change is worth a colour and indents a
 * part under its whole - all of which is written down once, in {@see Metric}. So the page renders the
 * catalog into the chart rather than the chart carrying a copy of it: the two numbers the switch used
 * to hold were small enough to mirror in javascript, fifty-four with a sentence each are not, and a
 * mirror is only ever as true as the day it was written.
 */
final readonly class MetricCatalog implements \JsonSerializable
{
    /**
     * @return list<array{slug: string, label: string, about: string, group: string, groupLabel: string, groupAbout: string, default: bool, decimals: int, direction: string, level: bool, partOf: string|null, depth: int}>
     */
    public function jsonSerialize(): array
    {
        return array_values(array_map(static function (Metric $metric) {
            $whole = $metric->partOf();

            return [
                'slug' => $metric->value,
                'label' => $metric->label(),
                'about' => $metric->about(),
                'group' => $metric->group()->value,
                'groupLabel' => $metric->group()->label(),
                'groupAbout' => $metric->group()->about(),
                // the metric a chart is drawn as while its address says nothing about it
                'default' => Metric::default() === $metric,
                'decimals' => $metric->decimals(),
                'direction' => $metric->direction()->value,
                'level' => $metric->carriesLevel(),
                'partOf' => $whole?->value,
                // how deep the number stands under its section, the way phploc indents its output
                'depth' => self::depth($metric),
            ];
        }, Metric::cases()));
    }

    private static function depth(Metric $metric): int
    {
        $depth = 0;

        while (null !== $metric = $metric->partOf()) {
            ++$depth;
        }

        return $depth;
    }
}
