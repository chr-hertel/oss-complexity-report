<?php

declare(strict_types=1);

namespace App\ComplexityReport;

use App\Entity\Repository;
use App\Entity\Tag;

final class GraphData implements \JsonSerializable
{
    private const DATE_FORMAT = 'm-d-y';

    public function __construct(private Repository $repository)
    {
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
     * `x` is what the chart plots, `date` and `loc` are what the release analysis below it reads.
     *
     * @return list<array{name: string, x: string, date: string, y: float, loc: int}>
     */
    public function getTagData(): array
    {
        return array_values(array_map(static function (Tag $tag) {
            return [
                'name' => $tag->getName(),
                'x' => $tag->getCreated()->format(self::DATE_FORMAT),
                'date' => $tag->getCreated()->format('Y-m-d'),
                'y' => round($tag->getAverageComplexity(), 2),
                'loc' => $tag->getLinesOfCode(),
            ];
        }, $this->repository->getTags()));
    }

    /**
     * @return array{name: string, tags: list<array{name: string, x: string, date: string, y: float, loc: int}>, labels: list<string>}
     */
    public function jsonSerialize(): array
    {
        return [
            // the select box addresses a repository by its slug, which is the name it is drawn under
            'name' => $this->repository->getName(),
            'tags' => $this->getTagData(),
            'labels' => $this->getLabels(),
        ];
    }
}
