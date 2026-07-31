<?php

declare(strict_types=1);

namespace App\Tests\ComplexityReport;

use App\ComplexityReport\Analysis;
use App\ComplexityReport\ChartSelection;
use App\ComplexityReport\GitTag;
use App\Entity\Organization;
use App\Entity\Repository;
use PHPUnit\Framework\TestCase;

final class ChartSelectionTest extends TestCase
{
    public function testTheDefaultChartIsTheMostStarredRepositories(): void
    {
        $analysed = [self::analysed('symfony/console'), self::analysed('laravel/framework'), self::analysed('symfony/http-kernel')];

        $selection = ChartSelection::mostStarred($analysed, 2);

        self::assertSame([$analysed[0], $analysed[1]], $selection->getSeries());
        self::assertSame('Overview', $selection->getEyebrow());
        self::assertSame('Most starred repositories', $selection->getHeadline());
        self::assertNull($selection->getUrl());
    }

    public function testASingleRepositoryNamesTheChart(): void
    {
        $repository = self::analysed('symfony/console');

        $selection = ChartSelection::of([$repository], [$repository]);

        self::assertSame('Repository', $selection->getEyebrow());
        self::assertSame('symfony/console', $selection->getHeadline());
        self::assertSame('https://github.com/symfony/console', $selection->getUrl());
    }

    public function testRepositoriesOfOneAccountAreNamedByTheirOwner(): void
    {
        $console = self::analysed('symfony/console');
        $kernel = self::analysed('symfony/http-kernel', $console->getOrganization());

        $selection = ChartSelection::of([$console, $kernel], [$console, $kernel]);

        self::assertSame('GitHub owner', $selection->getEyebrow());
        self::assertSame('symfony', $selection->getHeadline());
        self::assertSame('https://github.com/symfony', $selection->getUrl());
    }

    public function testAChartOfSeveralAccountsIsAComparison(): void
    {
        $selected = [self::analysed('symfony/console'), self::analysed('laravel/framework')];

        $selection = ChartSelection::of($selected, $selected);

        self::assertSame('Comparison', $selection->getEyebrow());
        self::assertSame('2 repositories compared', $selection->getHeadline());
        self::assertNull($selection->getUrl());
    }

    public function testTheSelectedRepositoriesComeFirstAndKeepTheirOrder(): void
    {
        $console = self::analysed('symfony/console');
        $framework = self::analysed('laravel/framework');
        $kernel = self::analysed('symfony/http-kernel');

        $selection = ChartSelection::of([$kernel, $console], [$console, $framework, $kernel]);

        self::assertSame([$kernel, $console, $framework], $selection->getOptions());
    }

    public function testARepositoryWithoutReleasesIsWaitedForInsteadOfDrawn(): void
    {
        $console = self::analysed('symfony/console');
        $queued = self::repository('symfony/uid');

        $selection = ChartSelection::of([$queued, $console], [$console]);

        self::assertSame([$console], $selection->getSeries());
        self::assertSame([$queued], $selection->getWaiting());
        // it has nothing to plot, so it is not an option of the chart either
        self::assertSame([$console], $selection->getOptions());
    }

    public function testARepositoryThatIsHalfwayThroughIsDrawnAndWaitedFor(): void
    {
        $running = self::measured('symfony/console');

        $selection = ChartSelection::of([$running], [$running]);

        self::assertSame([$running], $selection->getSeries());
        self::assertSame([$running], $selection->getWaiting());
    }

    /**
     * A repository that was submitted and is waiting for its first release.
     */
    private static function repository(string $name, ?Organization $organization = null): Repository
    {
        return new Repository(
            $name,
            sprintf('https://github.com/%s', $name),
            sprintf('https://github.com/%s.git', $name),
            $organization ?? new Organization(strstr($name, '/', true) ?: $name),
        );
    }

    /**
     * A repository with a release measured, still in the hands of a worker.
     */
    private static function measured(string $name, ?Organization $organization = null): Repository
    {
        $repository = self::repository($name, $organization);
        $repository->addTag(new GitTag('1.0'), new Analysis(100, 2.0, new \DateTimeImmutable('2020-01-01')));

        return $repository;
    }

    private static function analysed(string $name, ?Organization $organization = null): Repository
    {
        $repository = self::measured($name, $organization);
        $repository->markAnalysed();

        return $repository;
    }
}
