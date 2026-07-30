<?php

declare(strict_types=1);

namespace App\ComplexityReport;

use App\Entity\Library;

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
    public function loadTags(Library $library): array
    {
        $repository = $this->getRepository($library);
        $this->git->run($repository, 'fetch', '--all', '--tags', '--force');

        return GitTag::fromNames($this->git->listTags($repository));
    }

    public function checkoutTag(Library $library, string $name): void
    {
        $this->git->run($this->getRepository($library), 'checkout', '--force', $name);
    }

    public function getLastCommitDate(Library $library): \DateTimeImmutable
    {
        $date = $this->git->run($this->getRepository($library), 'log', '-1', '--format=%aI');

        return new \DateTimeImmutable(trim($date));
    }

    private function getRepository(Library $library): string
    {
        $localPath = $this->repositoryPath.'/'.$library->getRepositoryPath();

        if (!is_dir($localPath)) {
            $this->git->cloneRepository($library->getRepositoryUrl(), $localPath);
        }

        return $localPath;
    }
}
