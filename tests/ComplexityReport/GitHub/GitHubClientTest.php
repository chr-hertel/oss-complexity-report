<?php

declare(strict_types=1);

namespace App\Tests\ComplexityReport\GitHub;

use App\ComplexityReport\Exception\SubmissionFailed;
use App\ComplexityReport\GitHub\GitHubClient;
use App\ComplexityReport\GitHub\RepositoryIdentifier;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class GitHubClientTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $requested = [];

    /**
     * `/repos/a/b/languages` and `/repos/a/b_languages` are two different requests - a repository may be
     * named with the character the parts of a path are joined by, so they must not share a cache entry.
     */
    public function testTwoPathsThatReadAlikeAreCachedApart(): void
    {
        $client = $this->client();

        $client->getPhpShare(RepositoryIdentifier::fromInput('a/b'));
        $repository = $client->getRepository(RepositoryIdentifier::fromInput('a/b_languages'));

        self::assertSame(['/repos/a/b/languages', '/repos/a/b_languages'], $this->requested);
        self::assertSame('a/b_languages', (string) $repository->identifier);
    }

    public function testItAsksOnceForTheSameRepository(): void
    {
        $client = $this->client();
        $identifier = RepositoryIdentifier::fromInput('a/b');

        $client->getRepository($identifier);
        $client->getRepository($identifier);

        self::assertSame(['/repos/a/b'], $this->requested);
    }

    /**
     * A name github.com does not know spends the api quota like any other - so it is remembered too.
     */
    public function testItRemembersThatARepositoryDoesNotExist(): void
    {
        $client = $this->client(missing: true);
        $identifier = RepositoryIdentifier::fromInput('nobody/nothing');

        for ($attempt = 1; $attempt <= 3; ++$attempt) {
            try {
                $client->getRepository($identifier);
                self::fail('Expected the repository to be rejected as unknown.');
            } catch (SubmissionFailed $exception) {
                self::assertStringContainsString('nobody/nothing', $exception->getMessage());
            }
        }

        self::assertSame(['/repos/nobody/nothing'], $this->requested);
    }

    private function client(bool $missing = false): GitHubClient
    {
        $httpClient = new MockHttpClient(function (string $method, string $url) use ($missing): MockResponse {
            $path = (string) parse_url($url, \PHP_URL_PATH);
            $this->requested[] = $path;

            if ($missing) {
                return new MockResponse('{"message": "Not Found"}', ['http_code' => 404]);
            }

            if (str_ends_with($path, '/languages')) {
                return new MockResponse((string) json_encode(['PHP' => 100]));
            }

            return new MockResponse((string) json_encode([
                'name' => basename($path),
                'owner' => ['login' => 'a'],
                'html_url' => 'https://github.com'.$path,
                'clone_url' => 'https://github.com'.$path.'.git',
                'description' => null,
                'stargazers_count' => 1,
                'fork' => false,
                'size' => 42,
            ]));
        });

        return new GitHubClient($httpClient, new ArrayAdapter(), '', new NullLogger());
    }
}
