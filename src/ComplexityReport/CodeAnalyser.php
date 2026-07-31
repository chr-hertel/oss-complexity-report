<?php

declare(strict_types=1);

namespace App\ComplexityReport;

use App\Entity\Repository;
use SebastianBergmann\PHPLOC\Analyser;

final class CodeAnalyser
{
    public function __construct(
        private GitController $gitController,
        private SourceFiles $sourceFiles,
        private string $repositoryPath,
    ) {
    }

    public function analyse(Repository $repository): Analysis
    {
        $localPath = $this->repositoryPath.'/'.$repository->getLocalPath();
        $files = $this->sourceFiles->collect($localPath, $repository->getName());

        $analysis = (new Analyser())->countFiles($files, false);

        return new Analysis($analysis['loc'], $analysis['classCcnAvg'], $this->gitController->getLastCommitDate($repository));
    }
}
