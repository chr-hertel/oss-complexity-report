<?php

declare(strict_types=1);

namespace App\ComplexityReport;

use App\ComplexityReport\Metric\Measurement;
use App\ComplexityReport\Metric\Metric;
use App\Entity\Repository;
use App\Entity\Tag;

final class GraphData implements \JsonSerializable
{
    private const DATE_FORMAT = 'm-d-y';

    /**
     * @param list<Metric> $metrics the numbers this line is asked for - a release was measured in
     *                              sixty-two of them and a chart draws one or two, so what travels is
     *                              what is drawn
     */
    public function __construct(
        private Repository $repository,
        private array $metrics,
    ) {
    }

    /**
     * @return list<string>
     */
    public function getLabels(): array
    {
        return array_values(array_unique(array_map(static function (Tag $tag) {
            return $tag->getCreated()->format(self::DATE_FORMAT);
        }, $this->repository->getTags())));
    }

    /**
     * `x` is where a release sits on the time axis, `date` is what the release analysis prints, and
     * `values` are the numbers it is drawn as, keyed by the slug they are picked under.
     *
     * What is deliberately not in here is the whole measurement: sixty-two numbers per release would be
     * most of what a chart of fifty repositories transfers, for a panel that is read one release at a
     * time. A release the reader opens is fetched with everything phploc counted; a release the chart
     * only draws is worth the numbers it is drawn as.
     *
     * @return list<array{name: string, x: string, date: string, values: array<string, float|int|null>}>
     */
    public function getTagData(): array
    {
        return array_values(array_map(function (Tag $tag) {
            return [
                'name' => $tag->getName(),
                'x' => $tag->getCreated()->format(self::DATE_FORMAT),
                'date' => $tag->getCreated()->format('Y-m-d'),
                'values' => (new Measurement($tag->getMetrics()))->values($this->metrics),
            ];
        }, $this->repository->getTags()));
    }

    /**
     * A line of the chart: what it is called, where it comes from, which numbers it carries and the
     * releases it is drawn from. `url` and `stars` are what the release analysis says about the
     * repository behind the line.
     *
     * `metrics` is what was asked for, so the browser can tell a line it may draw from one it has to
     * ask for another number for.
     *
     * @return array{name: string, url: string, stars: int, metrics: list<string>, tags: list<array{name: string, x: string, date: string, values: array<string, float|int|null>}>, labels: list<string>}
     */
    public function jsonSerialize(): array
    {
        return [
            // the select box addresses a repository by its slug, which is the name it is drawn under
            'name' => $this->repository->getName(),
            'url' => $this->repository->getUrl(),
            'stars' => $this->repository->getStars(),
            'metrics' => array_map(static fn (Metric $metric) => $metric->value, $this->metrics),
            'tags' => $this->getTagData(),
            'labels' => $this->getLabels(),
        ];
    }
}
