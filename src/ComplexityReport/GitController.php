<?php

declare(strict_types=1);

namespace App\ComplexityReport;

use App\Entity\Library;
use GitWrapper\GitTags;
use GitWrapper\GitWorkingCopy;
use GitWrapper\GitWrapper;

class GitController
{
    private GitWrapper $gitWrapper;
    private string $repositoryPath;

    public function __construct(GitWrapper $gitWrapper, string $repositoryPath)
    {
        $this->gitWrapper = $gitWrapper;
        $this->repositoryPath = $repositoryPath;
    }

    public function loadTags(Library $library): GitTags
    {
        $repository = $this->getRepository($library);

        return $repository->tags();
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
