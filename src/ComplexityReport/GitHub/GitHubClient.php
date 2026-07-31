<?php

declare(strict_types=1);

namespace App\ComplexityReport\GitHub;

use App\ComplexityReport\Exception\SubmissionFailed;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Read-only access to the public github.com API - the only source of repositories of this application.
 */
final class GitHubClient
{
    private const string API_URL = 'https://api.github.com';
    private const int CACHE_TTL = 3600;
    private const int LOG_RESPONSE_LENGTH = 500;

    /**
     * A name github.com does not know is remembered too, and for much shorter.
     *
     * The api quota is what the whole report runs on, and a request for a repository that does not exist
     * spends it just like any other - so asking for names that never existed must not be free. Short,
     * because a repository that gets created should not stay unknown for the rest of the hour.
     */
    private const int MISSING_CACHE_TTL = 300;

    /**
     * Marks a cached miss. Not a field github.com sends, so it cannot be one of its own answers.
     */
    private const string MISSING = '__not_found';

    public function __construct(
        private HttpClientInterface $httpClient,
        private CacheItemPoolInterface $cache,
        private string $githubToken,
        private LoggerInterface $logger,
    ) {
    }

    public function getRepository(RepositoryIdentifier $identifier, bool $fresh = false): RepositoryData
    {
        $data = $this->request(sprintf('/repos/%s', $identifier), $identifier, $fresh);

        return RepositoryData::fromApiResponse($data);
    }

    public function getOwner(string $login, bool $fresh = false): OwnerData
    {
        $data = $this->request(sprintf('/users/%s', $login), $login, $fresh);

        return OwnerData::fromApiResponse($data);
    }

    /**
     * Share of PHP within the code base of a repository, between 0 and 1.
     */
    public function getPhpShare(RepositoryIdentifier $identifier): float
    {
        /** @var array<string, int> $languages */
        $languages = $this->request(sprintf('/repos/%s/languages', $identifier), $identifier);
        $total = array_sum($languages);

        if (0 === $total) {
            return 0.0;
        }

        return ($languages['PHP'] ?? 0) / $total;
    }

    /**
     * @return array<string, mixed>
     */
    private function request(string $path, string|\Stringable $subject, bool $fresh = false): array
    {
        $item = $this->cache->getItem($this->cacheKey($path));

        if ($fresh) {
            $this->cache->deleteItem($item->getKey());
            $item = $this->cache->getItem($item->getKey());
        }

        if (!$item->isHit()) {
            $fetched = $this->fetch($path, (string) $subject);

            $item->set($fetched);
            $item->expiresAfter(isset($fetched[self::MISSING]) ? self::MISSING_CACHE_TTL : self::CACHE_TTL);
            $this->cache->save($item);
        }

        /** @var array<string, mixed> $data */
        $data = $item->get();

        if (isset($data[self::MISSING])) {
            throw SubmissionFailed::unknownRepository((string) $subject);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetch(string $path, string $subject): array
    {
        $headers = [
            'Accept' => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => '2022-11-28',
            'User-Agent' => 'oss-complexity-report',
        ];

        if ('' !== $this->githubToken) {
            $headers['Authorization'] = sprintf('Bearer %s', $this->githubToken);
        }

        try {
            return $this->httpClient->request('GET', self::API_URL.$path, ['headers' => $headers])->toArray();
        } catch (ClientExceptionInterface $exception) {
            $response = $exception->getResponse();

            if (404 === $response->getStatusCode()) {
                return [self::MISSING => true];
            }

            // Whoever submitted the repository is told to try again later, but a refusal is rarely
            // temporary: an exhausted rate limit, a token an organization does not accept. github.com
            // says which one it is, and this is the only place that still knows.
            $this->logger->warning('GitHub refused {path} with {status}: {response}', [
                'path' => $path,
                'status' => $response->getStatusCode(),
                'response' => mb_substr($response->getContent(false), 0, self::LOG_RESPONSE_LENGTH),
            ]);

            throw SubmissionFailed::gitHubUnavailable($subject, $exception);
        } catch (ExceptionInterface $exception) {
            $this->logger->warning('GitHub request to {path} failed: {error}', [
                'path' => $path,
                'error' => $exception->getMessage(),
            ]);

            throw SubmissionFailed::gitHubUnavailable($subject, $exception);
        }
    }

    /**
     * The path is hashed rather than spelled out.
     *
     * A repository may be named with every character a path could be joined by, so `/repos/a/b/languages`
     * and `/repos/a/b_languages` used to share one entry and one of them was answered with the other one's
     * response. A hash cannot collide by naming, and it stays within the characters PSR-6 asks a pool to
     * accept - which the separator that would read nicer here does not.
     */
    private function cacheKey(string $path): string
    {
        return 'github_'.hash('xxh128', trim($path, '/'));
    }
}
