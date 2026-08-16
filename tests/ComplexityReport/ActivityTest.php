<?php

declare(strict_types=1);

namespace App\Tests\ComplexityReport;

use App\ComplexityReport\Activity;
use App\ComplexityReport\Analysis;
use App\ComplexityReport\GitTag;
use App\Entity\Organization;
use App\Entity\Repository;
use App\Entity\Tag;
use PHPUnit\Framework\TestCase;

final class ActivityTest extends TestCase
{
    public function testWaitingRepositoriesComeFirstAndReleasesFillWhatIsLeft(): void
    {
        $feed = Activity::feed(
            [self::queued('symfony/uid'), self::analysed('symfony/console')],
            [self::release('laravel/framework', 'v11.9'), self::release('guzzle/guzzle', '7.9')],
            8,
        );

        self::assertSame(
            ['symfony/uid', 'laravel/framework', 'guzzle/guzzle'],
            array_map(static fn (Activity $entry) => $entry->repository, $feed),
        );
        self::assertSame(['queued', 'v11.9', '7.9'], array_map(static fn (Activity $entry) => $entry->value, $feed));
        self::assertSame([true, false, false], array_map(static fn (Activity $entry) => $entry->state, $feed));
    }

    /**
     * A repository that is measured has nothing outstanding - what it did is the releases below it.
     */
    public function testARepositoryThatIsThroughIsNotOnTheStrip(): void
    {
        $feed = Activity::feed([self::analysed('symfony/console')], [], 8);

        self::assertSame([], $feed);
    }

    public function testARepositoryWithItsFirstReleasesInIsBeingAnalysed(): void
    {
        $feed = Activity::feed([self::measured('symfony/uid')], [], 8);

        self::assertSame('analysing', $feed[0]->value);
        self::assertTrue($feed[0]->state);
    }

    /**
     * A worker measures a repository release by release, so the newest releases the report has are
     * usually all of the same one. The strip is what came in, not how often it came in.
     */
    public function testARepositoryIsNamedOnce(): void
    {
        $feed = Activity::feed(
            [],
            [self::release('symfony/symfony', 'v7.2'), self::release('symfony/symfony', 'v7.1')],
            8,
        );

        self::assertCount(1, $feed);
        self::assertSame('v7.2', $feed[0]->value);
    }

    /**
     * A repository whose first releases just arrived would otherwise stand on the strip twice, saying
     * two different things about itself.
     */
    public function testAReleaseOfSomethingStillWaitingDoesNotRepeatIt(): void
    {
        $waiting = self::measured('symfony/uid');

        $feed = Activity::feed([$waiting], [self::release('symfony/uid', '7.3')], 8);

        self::assertCount(1, $feed);
        self::assertSame('analysing', $feed[0]->value);
    }

    public function testItStopsAtTheLimit(): void
    {
        $releases = array_map(
            static fn (int $index) => self::release(sprintf('vendor/repository-%d', $index), '1.0'),
            range(1, 12),
        );

        self::assertCount(3, Activity::feed([], $releases, 3));
    }

    /**
     * The limit bounds the strip, not what is still owed an answer: a report with more repositories
     * waiting than the strip has room for says so rather than showing releases instead.
     */
    public function testWaitingRepositoriesAreNotCrowdedOutByReleases(): void
    {
        $feed = Activity::feed(
            [self::queued('a/one'), self::queued('a/two'), self::queued('a/three')],
            [self::release('b/measured', '1.0')],
            2,
        );

        self::assertSame(['a/one', 'a/two'], array_map(static fn (Activity $entry) => $entry->repository, $feed));
    }

    private static function queued(string $name): Repository
    {
        return new Repository(
            $name,
            sprintf('https://github.com/%s', $name),
            sprintf('https://github.com/%s.git', $name),
            new Organization(strstr($name, '/', true) ?: $name),
        );
    }

    private static function measured(string $name): Repository
    {
        $repository = self::queued($name);
        $repository->addTag(new GitTag('1.0'), new Analysis(100, 2.0, new \DateTimeImmutable('2020-01-01'), []));

        return $repository;
    }

    private static function analysed(string $name): Repository
    {
        $repository = self::measured($name);
        $repository->markAnalysed();

        return $repository;
    }

    private static function release(string $repository, string $name): Tag
    {
        return new Tag($name, new \DateTimeImmutable('2024-11-29'), 100, 2.0, self::queued($repository), []);
    }
}
