<?php

declare(strict_types=1);

namespace App\ComplexityReport;

use App\Entity\Library;
use App\Entity\Tag;

final class GraphData implements \JsonSerializable
{
    private const DATE_FORMAT = 'm-d-y';

    public function __construct(private Library $library)
    {
    }

    /**
     * @return list<string>
     */
    public function getLabels(): array
    {
        return array_values(array_unique(array_map(static function (Tag $tag) {
            return $tag->getCreated()->format(self::DATE_FORMAT);
        }, $this->library->getTags())));
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
        }, $this->library->getTags()));
    }

    /**
     * @return array{name: string, tags: list<array{name: string, x: string, y: float}>, labels: list<string>}
     */
    public function jsonSerialize(): array
    {
        return [
            'name' => $this->library->getName(),
            'tags' => $this->getTagData(),
            'labels' => $this->getLabels(),
        ];
    }
}
