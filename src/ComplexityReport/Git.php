<?php

declare(strict_types=1);

namespace App\ComplexityReport;

use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

final readonly class Git
{
    public function __construct(
        private LoggerInterface $gitLogger,
    ) {
    }

    public function cloneRepository(string $url, string $path): void
    {
        $this->run(null, 'clone', $url, $path);
    }

    /**
     * @return list<string>
     */
    public function listTags(string $path): array
    {
        $tags = preg_split('/\R/', trim($this->run($path, 'tag')), -1, PREG_SPLIT_NO_EMPTY);

        return false === $tags ? [] : $tags;
    }

    public function run(?string $workingDirectory, string ...$arguments): string
    {
        $this->gitLogger->debug(sprintf('git %s', implode(' ', $arguments)));

        $process = new Process(['git', ...$arguments], $workingDirectory);
        $process->setTimeout(null);

        return $process->mustRun()->getOutput();
    }
}
