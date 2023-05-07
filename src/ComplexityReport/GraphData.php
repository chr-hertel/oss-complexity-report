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

    public function getLabels(): array
    {
        return array_values(array_unique(array_map(static function (Tag $tag) {
            return $tag->getCreated()->format(self::DATE_FORMAT);
        }, $this->library->getTags())));
    }

    public function getTagData(): array
    {
        return array_map(static function (Tag $tag) {
            return [
                'name' => $tag->getName(),
                'x' => $tag->getCreated()->format(self::DATE_FORMAT),
                'y' => round($tag->getAverageComplexity(), 2),
            ];
        }, $this->library->getTags());
    }

    public function jsonSerialize(): array
    {
        return [
            'name' => $this->library->getName(),
            'tags' => $this->getTagData(),
            'labels' => $this->getLabels(),
        ];
    }
}
