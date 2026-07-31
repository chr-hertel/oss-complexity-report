<?php

declare(strict_types=1);

namespace App\ComplexityReport;

use App\Entity\Repository;
use SebastianBergmann\PHPLOC\Analyser;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

final class CodeAnalyser
{
    public function __construct(
        private GitController $gitController,
        private string $repositoryPath,
    ) {
    }

    public function analyse(Repository $repository): Analysis
    {
        $localPath = $this->repositoryPath.'/'.$repository->getLocalPath();
        $finder = (new Finder())
            ->files()
            ->in($localPath)
            ->name('*.php');

        $files = array_map(static function (SplFileInfo $file) {
            return $file->getRealPath();
        }, iterator_to_array($finder));

        $analysis = (new Analyser())->countFiles($files, false);

        return new Analysis($analysis['loc'], $analysis['classCcnAvg'], $this->gitController->getLastCommitDate($repository));
    }
}
