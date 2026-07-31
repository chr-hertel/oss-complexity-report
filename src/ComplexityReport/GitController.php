<?php

declare(strict_types=1);

namespace App\ComplexityReport;

use App\Entity\Repository;

final readonly class GitController
{
    public function __construct(
        private Git $git,
        private string $repositoryPath,
    ) {
    }

    /**
     * @return GitTag[]
     */
    public function loadTags(Repository $repository): array
    {
        $localPath = $this->getLocalPath($repository);
        $this->git->run($localPath, 'fetch', '--all', '--tags', '--force');

        return GitTag::fromNames($this->git->listTags($localPath));
    }

    /**
     * Tags of the repository on github.com - reads refs only, so checking for new releases neither
     * needs a clone nor touches the working copy of a repository that is being analysed.
     *
     * @return GitTag[]
     */
    public function loadRemoteTags(Repository $repository): array
    {
        return GitTag::fromRefs($this->git->listRemoteTags($repository->getCloneUrl()));
    }

    public function checkoutTag(Repository $repository, string $name): void
    {
        $this->git->run($this->getLocalPath($repository), 'checkout', '--force', $name);
    }

    /**
     * Date of the commit the repository currently points at - $skip walks further back in history.
     */
    public function getLastCommitDate(Repository $repository, int $skip = 0): \DateTimeImmutable
    {
        $arguments = ['log', '-1', '--format=%aI'];

        if ($skip > 0) {
            $arguments[] = sprintf('--skip=%d', $skip);
        }

        $date = $this->git->run($this->getLocalPath($repository), ...$arguments);

        return new \DateTimeImmutable(trim($date));
    }

    public function isCloned(Repository $repository): bool
    {
        return is_dir($this->repositoryPath.'/'.$repository->getLocalPath());
    }

    private function getLocalPath(Repository $repository): string
    {
        $localPath = $this->repositoryPath.'/'.$repository->getLocalPath();

        if (!is_dir($localPath)) {
            $this->git->cloneRepository($repository->getCloneUrl(), $localPath);
        }

        return $localPath;
    }
}
