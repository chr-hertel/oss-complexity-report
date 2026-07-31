<?php

declare(strict_types=1);

namespace App\Tests\ComplexityReport;

use App\ComplexityReport\GitTag;
use PHPUnit\Framework\TestCase;

final class GitTagTest extends TestCase
{
    public function testCreatesOneTagPerName(): void
    {
        $tags = GitTag::fromNames(['v1.0.0', 'v1.1.0']);

        self::assertCount(2, $tags);
        self::assertSame('v1.0.0', $tags[0]->getName());
        self::assertSame('v1.1.0', $tags[1]->getName());
    }

    public function testCreatesNoTagsWithoutNames(): void
    {
        self::assertSame([], GitTag::fromNames([]));
    }

    public function testReadsNamesFromRemoteRefs(): void
    {
        $tags = GitTag::fromRefs([
            "b8dc4dbb4d3e0e2f2b5eb4b9d5cf5a5d2c1e8f90\trefs/tags/v6.4.0",
            "1a2b3c4d5e6f708192a3b4c5d6e7f8091a2b3c4d\trefs/tags/v7.0.0",
        ]);

        self::assertCount(2, $tags);
        self::assertSame('v6.4.0', $tags[0]->getName());
        self::assertSame('v7.0.0', $tags[1]->getName());
    }

    public function testKeepsSlashesAndDotsInRemoteTagNames(): void
    {
        $tags = GitTag::fromRefs(["b8dc4db\trefs/tags/release/1.2.3"]);

        self::assertSame('release/1.2.3', $tags[0]->getName());
    }

    public function testIgnoresRefsThatAreNotTags(): void
    {
        $tags = GitTag::fromRefs([
            "b8dc4db\trefs/heads/main",
            '',
        ]);

        self::assertSame([], $tags);
    }

    /**
     * @dataProvider preReleases
     */
    public function testDetectsPreRelease(string $name, bool $expected): void
    {
        self::assertSame($expected, (new GitTag($name))->isPreRelease());
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function preReleases(): iterable
    {
        yield 'stable' => ['v6.4.0', false];
        yield 'beta' => ['v6.4.0-BETA1', true];
        yield 'release candidate' => ['v6.4.0-RC1', true];
    }

    /**
     * @dataProvider patchReleases
     */
    public function testDetectsPatchRelease(string $name, bool $expected): void
    {
        self::assertSame($expected, (new GitTag($name))->isPatchRelease());
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function patchReleases(): iterable
    {
        yield 'minor release' => ['v6.4.0', false];
        yield 'patch release' => ['v6.4.1', true];
        yield 'double digit patch' => ['v6.4.12', true];

        // projects without a composer.json are not necessarily semver - WordPress writes its minors as "6.8"
        yield 'two digit minor' => ['6.8', false];
        yield 'two digit minor with prefix' => ['v7.0', false];
        yield 'early version' => ['0.71', false];
        yield 'two digit patch' => ['6.8.1', true];
        yield 'named tag' => ['nightly', true];
        yield 'four digits' => ['1.2.3.4', true];
    }
}
