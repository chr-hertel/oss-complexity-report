<?php

declare(strict_types=1);

namespace App\Tests\ComplexityReport;

use App\ComplexityReport\SourceFiles;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Filesystem\Filesystem;

final class SourceFilesTest extends TestCase
{
    private string $workingCopy;
    private string $outside;
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $base = sys_get_temp_dir().'/source-files-'.bin2hex(random_bytes(6));

        $this->workingCopy = $base.'/working-copy';
        $this->outside = $base.'/outside';

        $this->filesystem->mkdir([$this->workingCopy.'/src', $this->outside]);
        $this->filesystem->dumpFile($this->workingCopy.'/src/code.php', '<?php class A {}');
        $this->filesystem->dumpFile($this->outside.'/secret.php', '<?php // none of our business');
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove(\dirname($this->workingCopy));
    }

    public function testItCollectsThePhpFilesOfTheWorkingCopy(): void
    {
        $this->filesystem->dumpFile($this->workingCopy.'/README.md', 'not php');

        self::assertSame([realpath($this->workingCopy.'/src/code.php')], $this->collect());
    }

    /**
     * `evil.php -> /dev/zero` is a commit git stores and checks out like any other, and phploc reads what
     * it is handed - so the file it points at has to be excluded before it is read, not while.
     */
    public function testItSkipsALinkToACharacterDevice(): void
    {
        if (!file_exists('/dev/zero')) {
            self::markTestSkipped('No character device to point at.');
        }

        $this->filesystem->symlink('/dev/zero', $this->workingCopy.'/evil.php');

        self::assertNotContains($this->workingCopy.'/evil.php', $this->collect());
        self::assertCount(1, $this->collect());
    }

    public function testItSkipsALinkOutOfTheWorkingCopy(): void
    {
        $this->filesystem->symlink($this->outside.'/secret.php', $this->workingCopy.'/leak.php');

        self::assertSame([realpath($this->workingCopy.'/src/code.php')], $this->collect());
    }

    public function testItSkipsALinkToNowhere(): void
    {
        $this->filesystem->symlink($this->workingCopy.'/gone.php', $this->workingCopy.'/broken.php');

        self::assertSame([realpath($this->workingCopy.'/src/code.php')], $this->collect());
    }

    /**
     * Repositories do link within themselves, and that is ordinary - the link leaving the working copy is
     * what makes one dangerous.
     */
    public function testItKeepsALinkWithinTheWorkingCopy(): void
    {
        $this->filesystem->symlink($this->workingCopy.'/src/code.php', $this->workingCopy.'/alias.php');

        self::assertCount(2, $this->collect());
    }

    public function testItSkipsAFileTooLargeToMeasure(): void
    {
        $this->filesystem->dumpFile($this->workingCopy.'/generated.php', str_repeat('a', 5 * 1024 * 1024));

        self::assertSame([realpath($this->workingCopy.'/src/code.php')], $this->collect());
    }

    /**
     * @return list<string>
     */
    private function collect(): array
    {
        return (new SourceFiles(new NullLogger()))->collect($this->workingCopy, 'owner/repository');
    }
}
