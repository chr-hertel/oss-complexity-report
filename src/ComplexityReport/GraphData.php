<?php

declare(strict_types=1);

namespace App\ComplexityReport;

use App\Entity\Library;
use App\Entity\Tag;

class GraphData
{
    private const DATE_FORMAT = 'm-d-y';

    private Library $library;

    public function __construct(Library $library)
    {
        $this->library = $library;
    }

    public function getLabels(): array
    {
        return array_unique(array_map(static function (Tag $tag) {
            return $tag->getCreated()->format(self::DATE_FORMAT);
        }, $this->library->getTags()));
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
}
