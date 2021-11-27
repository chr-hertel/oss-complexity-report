<?php

declare(strict_types=1);

namespace App\ComplexityReport;

use App\Entity\Library;
use GitWrapper\GitWorkingCopy;
use GitWrapper\GitWrapper;

class GitController
{
    public function __construct(private GitWrapper $gitWrapper, private string $repositoryPath)
    {
    }

    /**
     * @return GitTag[]
     */
    public function loadTags(Library $library): array
    {
        $repository = $this->getRepository($library);
        $repository->fetchAll(['tags' => true]);

        return GitTag::fromGitTags($repository->tags());
    }

    public function checkoutTag(Library $library, string $name): void
    {
        $repository = $this->getRepository($library);
        $repository->checkout($name);
    }

    private function getRepository(Library $library): GitWorkingCopy
    {
        $localPath = $this->repositoryPath.'/'.$library->getRepositoryPath();

        if (is_dir($localPath)) {
            return $this->gitWrapper->workingCopy($localPath);
        }

        return $this->gitWrapper->cloneRepository($library->getRepositoryUrl(), $localPath);
    }
}
