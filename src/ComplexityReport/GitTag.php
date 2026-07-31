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

    /**
     * Only major and minor releases end up in the report - projects write them as `6.3.0` (semver) as well as
     * `6.3` (e.g. WordPress). Everything that is not a plain version number is not charted at all.
     */
    public function isPatchRelease(): bool
    {
        if (1 !== preg_match('#^v?(\d+)\.(\d+)(?:\.(\d+))?$#', $this->name, $version)) {
            return true;
        }

        return isset($version[3]) && '0' !== $version[3];
    }
}
