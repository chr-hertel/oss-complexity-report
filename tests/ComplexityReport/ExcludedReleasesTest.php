<?php

declare(strict_types=1);

namespace App\Tests\ComplexityReport;

use App\ComplexityReport\ExcludedReleases;
use App\ComplexityReport\GitTag;
use App\Entity\Organization;
use App\Entity\Repository;
use PHPUnit\Framework\TestCase;

final class ExcludedReleasesTest extends TestCase
{
    public function testItExcludesTheReleasesOfTheRepositoryThatCarriesThem(): void
    {
        $excluded = new ExcludedReleases();
        $laravel = self::repository('laravel/framework');

        self::assertTrue($excluded->contains($laravel, new GitTag('v6.19.0')));
        self::assertFalse($excluded->contains($laravel, new GitTag('v6.18.0')));
    }

    public function testItExcludesNothingFromAnotherRepository(): void
    {
        $excluded = new ExcludedReleases();

        // the same version number of a repository that has nothing to do with it stays in the report
        self::assertFalse($excluded->contains(self::repository('symfony/console'), new GitTag('v6.19.0')));
    }

    public function testItAddressesRepositoriesTheWayGitHubDoes(): void
    {
        $excluded = new ExcludedReleases();

        self::assertTrue($excluded->contains(self::repository('Laravel/Framework'), new GitTag('v7.30.0')));
        self::assertSame([], $excluded->of('laravel/laravel'));
    }

    private static function repository(string $name): Repository
    {
        [$login] = explode('/', $name);

        return new Repository(
            $name,
            sprintf('https://github.com/%s', $name),
            sprintf('https://github.com/%s.git', $name),
            new Organization($login),
        );
    }
}
