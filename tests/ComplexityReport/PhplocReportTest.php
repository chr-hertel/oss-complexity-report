<?php

declare(strict_types=1);

namespace App\Tests\ComplexityReport;

use App\ComplexityReport\PhplocReport;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\PHPLOC\Analyser;
use Symfony\Component\Filesystem\Filesystem;

final class PhplocReportTest extends TestCase
{
    private string $directory;
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->directory = sys_get_temp_dir().'/phploc-report-'.bin2hex(random_bytes(6));

        $this->filesystem->dumpFile($this->directory.'/code.php', <<<'PHP'
            <?php

            final class Measured
            {
                public function branch(int $value): string
                {
                    return $value > 0 ? 'up' : 'down';
                }
            }
            PHP);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->directory);
    }

    public function testItPrintsTheMeasurementAsPhplocPrintsIt(): void
    {
        $output = (new PhplocReport())->render($this->measure());

        self::assertStringContainsString('Size', $output);
        self::assertStringContainsString('Cyclomatic Complexity', $output);
        self::assertStringContainsString('Structure', $output);
        self::assertStringContainsString('Lines of Code (LOC)', $output);
    }

    /**
     * The one thing that is not obvious about handing a stored measurement back to phploc: it has to
     * still be a whole one. The printer reads keys the analysis itself never looks at, so a measurement
     * stored with anything less than everything phploc counted prints warnings instead of a report.
     */
    public function testItPrintsEveryNumberOfTheMeasurement(): void
    {
        $output = (new PhplocReport())->render($this->measure());

        self::assertMatchesRegularExpression('/\n  Lines of Code \(LOC\) +8\n/', $output);
        self::assertMatchesRegularExpression('/\n  Classes +1\n/', $output);
        // the branch in the fixture, above the complexity of 1 a straight method carries
        self::assertMatchesRegularExpression('/\n    Maximum Method Complexity +2\.00\n/', $output);
    }

    /**
     * The report is read as a column of numbers, so nothing may be printed in front of it - a notice
     * about a key the measurement does not carry would be, and would be the first thing a visitor sees.
     * Which section opens the report is phploc's business: it leads with the directories it walked, and
     * a measurement of a single directory has none to lead with.
     */
    public function testItStartsWithTheReport(): void
    {
        $output = (new PhplocReport())->render($this->measure());

        self::assertMatchesRegularExpression('/^(Directories|Size)/', $output);
    }

    /**
     * @return array<string, float|int>
     */
    private function measure(): array
    {
        /** @var array<string, float|int> $metrics */
        $metrics = (new Analyser())->countFiles([$this->directory.'/code.php'], false);

        return $metrics;
    }
}
