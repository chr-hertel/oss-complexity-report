<?php

declare(strict_types=1);

namespace App\ComplexityReport;

use App\Entity\Library;
use SebastianBergmann\PHPLOC\Analyser;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class CodeAnalyser
{
    private Analyser $analyser;
    private string $repositoryPath;

    public function __construct(Analyser $analyser, string $repositoryPath)
    {
        $this->repositoryPath = $repositoryPath;
        $this->analyser = $analyser;
    }

    public function analyse(Library $library): Analysis
    {
        $localPath = $this->repositoryPath.'/'.$library->getRepositoryPath();
        $finder = (new Finder())
            ->in($localPath)
            ->name('*.php');

        $files = array_map(static function (SplFileInfo $file) {
            return $file->getRealPath();
        }, iterator_to_array($finder));

        $analysis = $this->analyser->countFiles($files, false);

        return new Analysis($analysis['loc'], $analysis['classCcnAvg']);
    }
}
