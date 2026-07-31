<?php

declare(strict_types=1);

namespace App\ComplexityReport;

use App\Entity\Repository;
use Psr\Log\LoggerInterface;

/**
 * Finds the releases of a repository that belong in the report but are not measured yet.
 */
final readonly class ReleaseScanner
{
    public function __construct(
        private GitController $gitController,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Asks github.com what it has - cheap enough to run over every repository each night.
     *
     * @return list<GitTag>
     */
    public function scanRemote(Repository $repository): array
    {
        return $this->newReleases($repository, $this->gitController->loadRemoteTags($repository));
    }

    /**
     * Asks the working copy, which is fetched first - used right before analysing it.
     *
     * @return list<GitTag>
     */
    public function scanWorkingCopy(Repository $repository): array
    {
        return $this->newReleases($repository, $this->gitController->loadTags($repository));
    }

    /**
     * @param GitTag[] $tags
     *
     * @return list<GitTag>
     */
    private function newReleases(Repository $repository, array $tags): array
    {
        $releases = [];

        foreach ($tags as $tag) {
            if ($tag->isPreRelease() || $tag->isPatchRelease()) {
                continue;
            }

            if ($repository->hasTag($tag->getName())) {
                continue;
            }

            $releases[] = $tag;
        }

        $this->logger->debug(sprintf(
            'Repository %s has %d new release(s) among %d tags',
            $repository->getName(),
            count($releases),
            count($tags)
        ));

        return $releases;
    }
}
