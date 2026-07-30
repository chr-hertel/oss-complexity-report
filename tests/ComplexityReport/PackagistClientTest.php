<?php

declare(strict_types=1);

namespace App\Tests\ComplexityReport;

use App\ComplexityReport\PackagistClient;
use App\Entity\Library;
use App\Entity\Project;
use App\Repository\LibraryRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class PackagistClientTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $requestedUrls = [];

    public function testLoadsRepositoryUrlOfEveryNewPackage(): void
    {
        $client = $this->createClient([
            'https://packagist.org/packages/list.json?vendor=acme' => ['packageNames' => ['acme/foo', 'acme/bar']],
            'https://repo.packagist.org/p2/acme/foo.json' => $this->packageMetadata('acme/foo', 'https://github.com/acme/foo.git'),
            'https://repo.packagist.org/p2/acme/bar.json' => $this->packageMetadata('acme/bar', 'https://github.com/acme/bar.git'),
        ]);

        $libraries = $client->fetchNewLibraries($this->project());

        self::assertCount(2, $libraries);
        self::assertSame('acme/foo', $libraries[0]->getName());
        self::assertSame('acme/bar', $libraries[1]->getName());
    }

    public function testStripsGitSuffixFromRepositoryUrl(): void
    {
        $client = $this->createClient([
            'https://packagist.org/packages/list.json?vendor=acme' => ['packageNames' => ['acme/foo']],
            'https://repo.packagist.org/p2/acme/foo.json' => $this->packageMetadata('acme/foo', 'https://github.com/acme/foo.git'),
        ]);

        $libraries = $client->fetchNewLibraries($this->project());

        self::assertSame('https://github.com/acme/foo', $libraries[0]->getRepositoryUrl());
    }

    public function testUsesComposerMetadataEndpoint(): void
    {
        $client = $this->createClient([
            'https://packagist.org/packages/list.json?vendor=acme' => ['packageNames' => ['acme/foo']],
            'https://repo.packagist.org/p2/acme/foo.json' => $this->packageMetadata('acme/foo', 'https://github.com/acme/foo.git'),
        ]);

        $client->fetchNewLibraries($this->project());

        self::assertContains('https://repo.packagist.org/p2/acme/foo.json', $this->requestedUrls);
    }

    public function testSkipsPackagesWithoutTaggedRelease(): void
    {
        $client = $this->createClient([
            'https://packagist.org/packages/list.json?vendor=acme' => ['packageNames' => ['acme/foo', 'acme/unreleased']],
            'https://repo.packagist.org/p2/acme/foo.json' => $this->packageMetadata('acme/foo', 'https://github.com/acme/foo.git'),
            'https://repo.packagist.org/p2/acme/unreleased.json' => ['packages' => ['acme/unreleased' => []]],
        ]);

        $libraries = $client->fetchNewLibraries($this->project());

        self::assertCount(1, $libraries);
        self::assertSame('acme/foo', $libraries[0]->getName());
    }

    public function testSkipsExcludedPackages(): void
    {
        $client = $this->createClient([
            'https://packagist.org/packages/list.json?vendor=typo3' => ['packageNames' => ['typo3/cms', 'typo3/cms-core']],
            'https://repo.packagist.org/p2/typo3/cms-core.json' => $this->packageMetadata('typo3/cms-core', 'https://github.com/TYPO3-CMS/core.git'),
        ]);

        $libraries = $client->fetchNewLibraries($this->project('TYPO3', 'typo3'));

        self::assertCount(1, $libraries);
        self::assertSame('typo3/cms-core', $libraries[0]->getName());
        self::assertNotContains('https://repo.packagist.org/p2/typo3/cms.json', $this->requestedUrls);
    }

    public function testSkipsPackagesAlreadyStored(): void
    {
        $project = $this->project();
        $client = $this->createClient([
            'https://packagist.org/packages/list.json?vendor=acme' => ['packageNames' => ['acme/foo', 'acme/bar']],
            'https://repo.packagist.org/p2/acme/bar.json' => $this->packageMetadata('acme/bar', 'https://github.com/acme/bar.git'),
        ], [new Library('acme/foo', 'https://github.com/acme/foo', $project)]);

        $libraries = $client->fetchNewLibraries($project);

        self::assertCount(1, $libraries);
        self::assertSame('acme/bar', $libraries[0]->getName());
    }

    public function testCachesPackagistResponses(): void
    {
        $client = $this->createClient([
            'https://packagist.org/packages/list.json?vendor=acme' => ['packageNames' => ['acme/foo']],
            'https://repo.packagist.org/p2/acme/foo.json' => $this->packageMetadata('acme/foo', 'https://github.com/acme/foo.git'),
        ]);

        $client->fetchNewLibraries($this->project());
        $client->fetchNewLibraries($this->project());

        self::assertCount(2, $this->requestedUrls);
    }

    /**
     * @param array<string, array> $responses
     * @param list<Library>        $storedLibraries
     */
    private function createClient(array $responses, array $storedLibraries = []): PackagistClient
    {
        $httpClient = new MockHttpClient(function (string $method, string $url) use ($responses): MockResponse {
            $this->requestedUrls[] = $url;

            self::assertArrayHasKey($url, $responses, sprintf('Unexpected request to "%s"', $url));

            return new MockResponse(
                json_encode($responses[$url], JSON_THROW_ON_ERROR),
                ['response_headers' => ['content-type' => 'application/json']]
            );
        });

        $repository = $this->createMock(LibraryRepository::class);
        $repository->method('findAll')->willReturn($storedLibraries);

        return new PackagistClient($httpClient, $repository, new ArrayAdapter());
    }

    private function project(string $name = 'Acme', string $vendor = 'acme'): Project
    {
        return new Project($name, sprintf('https://%s.com', $vendor), $vendor);
    }

    private function packageMetadata(string $package, string $sourceUrl): array
    {
        return ['packages' => [$package => [
            ['version' => '2.0.0', 'source' => ['type' => 'git', 'url' => $sourceUrl]],
            ['version' => '1.0.0', 'source' => ['type' => 'git', 'url' => $sourceUrl]],
        ]]];
    }
}
