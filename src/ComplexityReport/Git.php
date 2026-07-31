<?php

declare(strict_types=1);

namespace App\ComplexityReport;

use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

final readonly class Git
{
    /**
     * How long a single git call may take, in seconds.
     *
     * Cloning a large repository is a matter of minutes, so this is generous - it is not there to keep
     * git quick but to end the calls that never finish at all: a remote that accepts the connection and
     * then stalls would otherwise hold a worker until someone notices.
     */
    private const int TIMEOUT = 1800;

    public function __construct(
        private LoggerInterface $gitLogger,
    ) {
    }

    public function cloneRepository(string $url, string $path): void
    {
        // `--` so a url can never be read as an option, whatever github.com reports it as
        $this->run(null, 'clone', '--', $url, $path);
    }

    /**
     * @return list<string>
     */
    public function listTags(string $path): array
    {
        return $this->lines($this->run($path, 'tag'));
    }

    /**
     * Refs of a remote, without cloning it - `--refs` drops the `^{}` entries of annotated tags.
     *
     * @return list<string>
     */
    public function listRemoteTags(string $url): array
    {
        return $this->lines($this->run(null, 'ls-remote', '--tags', '--refs', '--', $url));
    }

    /**
     * @return list<string>
     */
    private function lines(string $output): array
    {
        $lines = preg_split('/\R/', trim($output), -1, PREG_SPLIT_NO_EMPTY);

        return false === $lines ? [] : $lines;
    }

    public function run(?string $workingDirectory, string ...$arguments): string
    {
        $this->gitLogger->debug(sprintf('git %s', implode(' ', $arguments)));

        // a repository that went private or was deleted must fail instead of waiting for credentials
        // that a worker process can never provide
        $process = new Process(['git', ...$arguments], $workingDirectory, ['GIT_TERMINAL_PROMPT' => '0']);
        $process->setTimeout(self::TIMEOUT);

        return $process->mustRun()->getOutput();
    }
}
