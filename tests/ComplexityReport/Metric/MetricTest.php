<?php

declare(strict_types=1);

namespace App\Tests\ComplexityReport\Metric;

use App\ComplexityReport\Metric\Metric;
use App\ComplexityReport\Metric\MetricGroup;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\PHPLOC\Analyser;
use Symfony\Component\Filesystem\Filesystem;

/**
 * The catalog says what a number is called and where it comes from. What it cannot say by itself is
 * whether the number is still there: `source()` names a key of a phploc measurement, and a metric whose
 * key phploc does not count is a chart of nulls. So the measurement these run against is a real one,
 * taken here, the way {@see \App\Tests\ComplexityReport\PhplocReportTest} takes one.
 */
final class MetricTest extends TestCase
{
    private string $directory;
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->directory = sys_get_temp_dir().'/metric-'.bin2hex(random_bytes(6));

        $this->filesystem->dumpFile($this->directory.'/code.php', <<<'PHP'
            <?php

            namespace Measured;

            final class Branching
            {
                public const LIMIT = 10;

                public function branch(int $value): string
                {
                    return $value > self::LIMIT ? 'over' : 'under';
                }
            }
            PHP);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->directory);
    }

    public function testEveryMetricIsCountedByPhploc(): void
    {
        $measurement = $this->measure();

        foreach (Metric::cases() as $metric) {
            self::assertArrayHasKey(
                $metric->source(),
                $measurement,
                sprintf('%s reads a key phploc does not count', $metric->name),
            );
        }
    }

    public function testTheMetricTheReportIsAboutIsTheAverageComplexityOfAClass(): void
    {
        // the number every trend, ranking and risk band of the report is computed from
        self::assertSame('classCcnAvg', Metric::default()->source());
        self::assertSame(MetricGroup::Complexity, Metric::default()->group());
        self::assertTrue(Metric::default()->carriesLevel());
    }

    /**
     * A slug is what a chart is shared as, and a key is what phploc stored - neither may be two things
     * at once, or a link would draw something else than it says.
     */
    public function testEveryMetricIsAddressedByOneSlugAndReadsOneKey(): void
    {
        $slugs = array_map(static fn (Metric $metric) => $metric->value, Metric::cases());
        $sources = array_map(static fn (Metric $metric) => $metric->source(), Metric::cases());

        self::assertSame($slugs, array_unique($slugs));
        self::assertSame($sources, array_unique($sources));
    }

    /**
     * A part is only a part of something in the same section: a percentage is read against the number
     * standing above it, which is what the indentation of the interpreted measurement says.
     */
    public function testAPartBelongsToTheSectionOfItsWhole(): void
    {
        foreach (Metric::cases() as $metric) {
            $whole = $metric->partOf();

            if (null !== $whole) {
                self::assertSame($whole->group(), $metric->group(), sprintf('%s is a part of another section', $metric->name));
                self::assertNotSame($whole, $metric);
            }
        }
    }

    public function testEveryMetricSaysWhatItIsAndWhatItIsWorth(): void
    {
        foreach (Metric::cases() as $metric) {
            self::assertNotSame('', $metric->label());
            self::assertNotSame('', $metric->about());
            self::assertStringEndsWith('.', $metric->about(), sprintf('%s does not explain itself in a sentence', $metric->name));
        }
    }

    public function testEverySectionCarriesItsMetricsInTheOrderOfTheCatalog(): void
    {
        $grouped = [];

        foreach (MetricGroup::cases() as $group) {
            self::assertNotSame([], $group->metrics());

            $grouped = [...$grouped, ...$group->metrics()];
        }

        // the catalog is the order the report reads a measurement in, and no metric falls out of it
        self::assertSame(Metric::cases(), $grouped);
    }

    /**
     * @return array<string, float|int>
     */
    private function measure(): array
    {
        /** @var array<string, float|int> $measurement */
        $measurement = (new Analyser())->countFiles([$this->directory.'/code.php'], false);

        return $measurement;
    }
}
