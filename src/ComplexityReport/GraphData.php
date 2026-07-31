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
     * @return list<array{name: string, x: string, y: float}>
     */
    public function getTagData(): array
    {
        return array_values(array_map(static function (Tag $tag) {
            return [
                'name' => $tag->getName(),
                'x' => $tag->getCreated()->format(self::DATE_FORMAT),
                'y' => round($tag->getAverageComplexity(), 2),
            ];
        }, $this->repository->getTags()));
    }

    /**
     * @return array{name: string, tags: list<array{name: string, x: string, y: float}>, labels: list<string>}
     */
    public function jsonSerialize(): array
    {
        return [
            'name' => $this->repository->getName(),
            'tags' => $this->getTagData(),
            'labels' => $this->getLabels(),
        ];
    }
}
