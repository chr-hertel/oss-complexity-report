<?php

declare(strict_types=1);

namespace App\ComplexityReport;

use App\Entity\Repository;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

final readonly class GitController
{
    public function __construct(
        private Git $git,
        private Filesystem $filesystem,
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

    /**
     * A tag is checked out under its full ref: what is measured should be the tag and never a branch that
     * happens to share its name, and a ref spelled out this way cannot be read as an option either.
     */
    public function checkoutTag(Repository $repository, string $name): void
    {
        $this->git->run($this->getLocalPath($repository), 'checkout', '--force', sprintf('refs/tags/%s', $name));
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

    /**
     * A working copy is scratch space: it is only needed while releases are measured, and looking for new
     * ones does not need it at all (see loadRemoteTags()). Dropping it afterwards bounds the disk by what
     * is being analysed instead of by everything ever submitted - a repository that releases again is
     * cloned again, which happens a few times a year, not nightly.
     */
    public function removeWorkingCopy(string $localPath): void
    {
        $path = $this->repositoryPath.'/'.$localPath;

        if (!is_dir($path)) {
            return;
        }

        $this->filesystem->remove($path);

        // git clone recreates the vendor directory, so an empty one left behind is just noise
        $vendorPath = \dirname($path);

        if ($this->isEmptyDirectory($vendorPath)) {
            $this->filesystem->remove($vendorPath);
        }
    }

    /**
     * Every working copy on disk as `vendor/repository`, including the ones left behind by repositories
     * that are not submitted under that name anymore.
     *
     * @return list<string>
     */
    public function listWorkingCopies(): array
    {
        if (!is_dir($this->repositoryPath)) {
            return [];
        }

        $finder = (new Finder())
            ->directories()
            ->in($this->repositoryPath)
            ->depth(1);

        $workingCopies = [];

        foreach ($finder as $directory) {
            $workingCopies[] = $directory->getRelativePathname();
        }

        sort($workingCopies);

        return $workingCopies;
    }

    private function isEmptyDirectory(string $path): bool
    {
        return is_dir($path) && !(new \FilesystemIterator($path))->valid();
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
