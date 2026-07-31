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
    private const API_URL = 'https://api.github.com';
    private const CACHE_TTL = 3600;
    private const LOG_RESPONSE_LENGTH = 500;

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
            $item->set($this->fetch($path, (string) $subject));
            $item->expiresAfter(self::CACHE_TTL);
            $this->cache->save($item);
        }

        /** @var array<string, mixed> $data */
        $data = $item->get();

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
                throw SubmissionFailed::unknownRepository($subject);
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

    private function cacheKey(string $path): string
    {
        return 'github_'.str_replace('/', '_', trim($path, '/'));
    }
}
