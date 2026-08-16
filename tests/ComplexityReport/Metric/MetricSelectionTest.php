<?php

declare(strict_types=1);

namespace App\Tests\ComplexityReport\Metric;

use App\ComplexityReport\Metric\Metric;
use App\ComplexityReport\Metric\MetricSelection;
use PHPUnit\Framework\TestCase;

final class MetricSelectionTest extends TestCase
{
    public function testTheChartNobodyToldAnythingIsTheMetricTheReportIsAbout(): void
    {
        $selection = MetricSelection::fromInput('');

        self::assertSame([Metric::Complexity], $selection->getPicked());
        self::assertSame(Metric::Complexity, $selection->getActive());
        self::assertTrue($selection->isDefault());
    }

    public function testTheOrderOfTheQueryStringIsTheOrderOfTheTabs(): void
    {
        $selection = MetricSelection::fromInput('loc,complexity');

        self::assertSame([Metric::LinesOfCode, Metric::Complexity], $selection->getPicked());
        self::assertSame(['loc', 'complexity'], $selection->getSlugs());
        self::assertFalse($selection->isDefault());
    }

    /**
     * A chart opens on its first tab, and says which one it opens on only when that is not it.
     */
    public function testTheFirstMetricIsTheOneTheChartOpensOn(): void
    {
        $selection = MetricSelection::fromInput('loc,complexity');

        self::assertSame(Metric::LinesOfCode, $selection->getActive());
        self::assertTrue($selection->isFirstActive());
    }

    public function testTheAddressCanNameTheTabThatIsOpen(): void
    {
        $selection = MetricSelection::fromInput('loc,complexity', 'complexity');

        self::assertSame([Metric::LinesOfCode, Metric::Complexity], $selection->getPicked());
        self::assertSame(Metric::Complexity, $selection->getActive());
        self::assertFalse($selection->isFirstActive());
    }

    /**
     * A tab that is not one of the chart's own is not a tab, so the chart opens where it would anyway -
     * an address is edited by hand and shared, and neither has to be right for it to draw something.
     */
    public function testAMetricTheChartDoesNotCarryCannotBeTheOpenTab(): void
    {
        $selection = MetricSelection::fromInput('loc,complexity', 'traits');

        self::assertSame(Metric::LinesOfCode, $selection->getActive());
    }

    /**
     * The query string is a link people read, edit and share, so what is not a metric of the report is
     * dropped the way a repository that is not in it is - an address that says something the report
     * cannot draw still draws what it can.
     */
    public function testWhatIsNotAMetricIsDropped(): void
    {
        $selection = MetricSelection::fromInput('loc,classCcnAvg,,complexity');

        self::assertSame([Metric::LinesOfCode, Metric::Complexity], $selection->getPicked());
    }

    public function testNothingLeftOfASelectionIsStillAChart(): void
    {
        self::assertSame([Metric::Complexity], MetricSelection::fromInput('nonsense')->getPicked());
    }

    public function testAMetricIsOneTabHoweverOftenItIsNamed(): void
    {
        $selection = MetricSelection::fromInput('LOC, loc ,loc');

        self::assertSame([Metric::LinesOfCode], $selection->getPicked());
        self::assertTrue($selection->has(Metric::LinesOfCode));
        self::assertFalse($selection->has(Metric::Complexity));
    }

    /**
     * The chart used to be a switch between two numbers, addressed in the singular. Those links were
     * handed out, and a switch between two is a selection of one.
     */
    public function testTheAddressOfTheSwitchThisGrewOutOfStillSaysWhatItMeant(): void
    {
        self::assertSame([Metric::LinesOfCode], MetricSelection::fromInput('', 'loc')->getPicked());
        self::assertSame([Metric::Complexity], MetricSelection::fromInput('', 'complexity')->getPicked());
    }

    public function testASelectionOverridesTheSwitchItGrewOutOf(): void
    {
        self::assertSame([Metric::Complexity], MetricSelection::fromInput('complexity', 'loc')->getPicked());
    }
}
