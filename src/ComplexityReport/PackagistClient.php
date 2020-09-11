<?php

declare(strict_types=1);

namespace App\ComplexityReport;

use App\Entity\Library;
use App\Entity\Project;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PackagistClient
{
    private const PACKAGE_DENY_LIST = [
        'doctrine/orm-g2s',
        'laravel/dbfactory',
        'laravel/liferaft',
        'symfony/annotations-pack',
        'typo3/cms',
        'typo3/expose',
        'typo3/formexample',
        'typo3/sitekickstarter',
    ];

    private HttpClientInterface $httpClient;
    private CacheItemPoolInterface $cache;

    public function __construct(HttpClientInterface $httpClient, CacheItemPoolInterface $cache)
    {
        $this->httpClient = $httpClient;
        $this->cache = $cache;
    }

    public function fetchLibraries(Project $project): array
    {
        $packages = $this->fetchPackages($project);

        $libraries = [];
        foreach ($packages as $package) {
            if (in_array($package, self::PACKAGE_DENY_LIST, true)) {
                continue;
            }

            $data = $this->fetchPackageData($package);
            $libraries[] = new Library($package, $data['package']['repository'], $project);
        }

        return $libraries;
    }

    private function fetchPackages(Project $project): array
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

    private function fetchPackageData(string $package): array
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
