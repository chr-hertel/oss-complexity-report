<?php

declare(strict_types=1);

namespace App\ComplexityReport;

use App\Entity\Library;
use GitWrapper\GitWrapper;
use SebastianBergmann\PHPLOC\Analyser;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class CodeAnalyser
{
    private GitWrapper $gitWrapper;
    private string $repositoryPath;

    public function __construct(GitWrapper $gitWrapper, string $repositoryPath)
    {
        $this->gitWrapper = $gitWrapper;
        $this->repositoryPath = $repositoryPath;
    }

    public function analyse(Library $library): Analysis
    {
        $localPath = $this->repositoryPath.'/'.$library->getRepositoryPath();
        $finder = (new Finder())
            ->files()
            ->in($localPath)
            ->name('*.php');

        $files = array_map(static function (SplFileInfo $file) {
            return $file->getRealPath();
        }, iterator_to_array($finder));

        $analysis = (new Analyser())->countFiles($files, false);

        $repository = $this->gitWrapper->workingCopy($localPath);
        $created = new \DateTimeImmutable($repository->log('-1', '--format=%ai'));

        return new Analysis($analysis['loc'], $analysis['classCcnAvg'], $created);
    }
}
