<?php

declare(strict_types=1);

namespace App\ComplexityReport;

final class GitTag
{
    public function __construct(private string $name)
    {
    }

    /**
     * @param list<string> $names
     *
     * @return self[]
     */
    public static function fromNames(array $names): array
    {
        return array_map(static function (string $name) {
            return new self($name);
        }, $names);
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
