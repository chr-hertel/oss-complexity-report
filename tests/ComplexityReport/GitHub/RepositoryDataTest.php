<?php

declare(strict_types=1);

namespace App\Tests\ComplexityReport\GitHub;

use App\ComplexityReport\GitHub\OwnerData;
use App\ComplexityReport\GitHub\RepositoryData;
use PHPUnit\Framework\TestCase;

final class RepositoryDataTest extends TestCase
{
    public function testItMapsARepositoryResponse(): void
    {
        $data = RepositoryData::fromApiResponse([
            'name' => 'console',
            'owner' => ['login' => 'symfony'],
            'html_url' => 'https://github.com/symfony/console',
            'clone_url' => 'https://github.com/symfony/console.git',
            'description' => 'Eases the creation of beautiful and testable command line interfaces',
            'stargazers_count' => 9678,
            'fork' => false,
            'size' => 4711,
        ]);

        self::assertSame('symfony/console', (string) $data->identifier);
        self::assertSame('symfony', $data->identifier->owner);
        self::assertSame('https://github.com/symfony/console.git', $data->cloneUrl);
        self::assertSame(9678, $data->stars);
        self::assertFalse($data->fork);
        self::assertFalse($data->empty);
    }

    public function testItDetectsEmptyRepositoriesAndMissingDescriptions(): void
    {
        $data = RepositoryData::fromApiResponse([
            'name' => 'empty',
            'owner' => ['login' => 'someone'],
            'html_url' => 'https://github.com/someone/empty',
            'clone_url' => 'https://github.com/someone/empty.git',
            'description' => null,
            'stargazers_count' => 0,
            'fork' => true,
            'size' => 0,
        ]);

        self::assertNull($data->description);
        self::assertTrue($data->empty);
        self::assertTrue($data->fork);
    }

    public function testItPrefersTheWebsiteOfAnOwner(): void
    {
        $owner = OwnerData::fromApiResponse([
            'login' => 'symfony',
            'name' => 'Symfony',
            'blog' => 'symfony.com',
            'html_url' => 'https://github.com/symfony',
            'avatar_url' => 'https://avatars.githubusercontent.com/u/143937',
        ]);

        self::assertSame('Symfony', $owner->name);
        self::assertSame('https://symfony.com', $owner->url);
    }

    public function testItFallsBackToTheGitHubProfileOfAnOwner(): void
    {
        $owner = OwnerData::fromApiResponse([
            'login' => 'Seldaek',
            'name' => null,
            'blog' => '',
            'html_url' => 'https://github.com/Seldaek',
            'avatar_url' => null,
        ]);

        self::assertSame('Seldaek', $owner->name);
        self::assertSame('https://github.com/Seldaek', $owner->url);
        self::assertNull($owner->avatarUrl);
    }
}
