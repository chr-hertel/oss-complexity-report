<?php

declare(strict_types=1);

namespace App\ComplexityReport\Trend;

use App\Repository\TagRepository;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * The trends of the start page: every release the report carries, rolled up per time frame.
 *
 * Reading every tag for a figure that only moves when a release is measured would be wasteful, so the
 * result is cached - keyed by the day, because the windows themselves move with it.
 */
final readonly class TrendLoader
{
    private const int TTL = 3600;

    public function __construct(
        private TagRepository $tagRepository,
        private TrendCalculator $calculator,
        private CacheInterface $cache,
    ) {
    }

    /**
     * @return list<Trend>
     */
    public function load(): array
    {
        $now = new \DateTimeImmutable();

        return $this->cache->get(
            sprintf('complexity_report.trends.%s', $now->format('Y-m-d')),
            function (ItemInterface $item) use ($now): array {
                $item->expiresAfter(self::TTL);

                return $this->calculator->calculate($this->tagRepository->findReleasePoints(), $now);
            }
        );
    }
}
