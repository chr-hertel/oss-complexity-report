<?php

declare(strict_types=1);

namespace App\ComplexityReport;

use GitWrapper\GitTags;

final class GitTag
{
    public function __construct(private string $name)
    {
    }

    /**
     * @return self[]
     */
    public static function fromGitTags(GitTags $tags): array
    {
        return array_map(static function (string $tagName) {
            return new self($tagName);
        }, iterator_to_array($tags));
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isPreRelease(): bool
    {
        return str_contains($this->name, '-');
    }

    public function isPatchRelease(): bool
    {
        return 0 !== substr_compare($this->name, '.0', -2);
    }
}
