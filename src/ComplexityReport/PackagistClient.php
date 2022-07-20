<?php

declare(strict_types=1);

namespace App\ComplexityReport;

use App\Entity\Library;
use App\Entity\Project;
use App\Repository\LibraryRepository;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class PackagistClient
{
    private const PACKAGE_DENY_LIST = [
        'doctrine/orm-g2s',
        'laravel/dbfactory',
        'laravel/liferaft',
        'symfony/annotations-pack',
        'symfony/webpack-encore-pack',
        'typo3/cms',
        'typo3/expose',
        'typo3/formexample',
        'typo3/sitekickstarter',
    ];

    public function __construct(
        private HttpClientInterface $httpClient,
        private LibraryRepository $repository,
        private CacheItemPoolInterface $cache,
    ) {
    }

    public function fetchNewLibraries(Project $project): array
    {
        $libraries = $this->fetchLibraries($project);
        $librariesToLoad = $this->filterNewLibraries($libraries);

        $newLibraries = [];
        foreach ($librariesToLoad as $library) {
            $data = $this->fetchLibraryData($library);
            $newLibraries[] = new Library($library, $data['package']['repository'], $project);
        }

        return $newLibraries;
    }

    private function fetchLibraries(Project $project): array
    {
        $item = $this->cache->getItem(sprintf('packages_%s', $project->getVendor()));

        if (!$item->isHit()) {
            $url = sprintf('https://packagist.org/packages/list.json?vendor=%s', $project->getVendor());
            $response = $this->httpClient->request('GET', $url);

            $item->set($response->toArray()['packageNames']);
            $item->expiresAfter(3600);
            $this->cache->save($item);
        }

        return $item->get();
    }

    private function filterNewLibraries(array $libraries): array
    {
        $existing = array_map(static function (Library $library) {
            return $library->getName();
        }, $this->repository->findAll());

        return array_filter($libraries, static function (string $library) use ($existing) {
            return !in_array($library, self::PACKAGE_DENY_LIST, true)
                && !in_array($library, $existing, true);
        });
    }

    private function fetchLibraryData(string $package): array
    {
        $item = $this->cache->getItem(sprintf('package_data_%s', str_replace('/', '_', $package)));

        if (!$item->isHit()) {
            $url = sprintf('https://repo.packagist.org/packages/%s.json', $package);
            $response = $this->httpClient->request('GET', $url);

            $item->set($response->toArray());
            $item->expiresAfter(3600);
            $this->cache->save($item);
        }

        return $item->get();
    }
}
