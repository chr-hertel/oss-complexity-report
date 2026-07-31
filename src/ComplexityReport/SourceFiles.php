<?php

declare(strict_types=1);

namespace App\ComplexityReport;

use Psr\Log\LoggerInterface;
use Symfony\Component\Finder\Finder;

/**
 * The PHP files of a working copy that may be measured.
 *
 * A submitted repository decides what its own files are, and git stores a symlink as faithfully as it
 * stores source code - `evil.php -> /dev/zero` is a perfectly valid commit. Whatever ends up in this list
 * is read into memory as a whole, so the list is what keeps a repository from reading the machine it is
 * measured on: everything the checkout put inside the working copy, and nothing else.
 */
final readonly class SourceFiles
{
    /**
     * Source files are measured as a whole, in memory - past a few megabytes it is generated data, not
     * code someone wrote, and not worth the memory of the worker that reads it.
     */
    private const int MAX_FILE_SIZE = 4 * 1024 * 1024;

    public function __construct(private LoggerInterface $logger)
    {
    }

    /**
     * @return list<string>
     */
    public function collect(string $workingCopy, string $repositoryName): array
    {
        $root = realpath($workingCopy);

        if (false === $root || !is_dir($root)) {
            return [];
        }

        $finder = (new Finder())
            ->files()
            ->in($root)
            ->name('*.php');

        $files = [];

        foreach ($finder as $candidate) {
            $path = realpath($candidate->getPathname());

            // a link to nowhere resolves to nothing, and a character device is not a regular file -
            // reading one of those would go on until the worker is out of memory
            if (false === $path || !is_file($path)) {
                continue;
            }

            if (!str_starts_with($path, $root.\DIRECTORY_SEPARATOR)) {
                $this->skip($candidate->getRelativePathname(), $repositoryName, sprintf('it points at %s', $path));

                continue;
            }

            $size = filesize($path);

            // a size that cannot be read is not a small file - this list decides what is read into
            // memory, so what it cannot measure it leaves out
            if (false === $size || $size > self::MAX_FILE_SIZE) {
                $this->skip(
                    $candidate->getRelativePathname(),
                    $repositoryName,
                    false === $size ? 'its size cannot be read' : 'it is too large to measure'
                );

                continue;
            }

            $files[] = $path;
        }

        sort($files);

        return $files;
    }

    private function skip(string $file, string $repositoryName, string $reason): void
    {
        $this->logger->warning(sprintf('Skipping %s of %s, %s', $file, $repositoryName, $reason));
    }
}
