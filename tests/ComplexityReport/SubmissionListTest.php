<?php

declare(strict_types=1);

namespace App\Tests\ComplexityReport;

use App\ComplexityReport\SubmissionList;
use PHPUnit\Framework\TestCase;

final class SubmissionListTest extends TestCase
{
    public function testReadsOneRepositoryPerLine(): void
    {
        $list = SubmissionList::fromText("symfony/console\nnikic/iter");

        self::assertSame(['symfony/console', 'nikic/iter'], $list->repositories);
        self::assertSame(2, $list->count());
    }

    public function testKeepsWhateverIdentifiesARepository(): void
    {
        $list = SubmissionList::fromText("https://github.com/symfony/console\ngit@github.com:nikic/iter.git");

        self::assertSame(
            ['https://github.com/symfony/console', 'git@github.com:nikic/iter.git'],
            $list->repositories
        );
    }

    public function testSkipsBlankLines(): void
    {
        $list = SubmissionList::fromText("symfony/console\n\n   \n\nnikic/iter\n");

        self::assertSame(['symfony/console', 'nikic/iter'], $list->repositories);
    }

    public function testTrimsSurroundingWhitespace(): void
    {
        $list = SubmissionList::fromText("  symfony/console  \n\tnikic/iter\t");

        self::assertSame(['symfony/console', 'nikic/iter'], $list->repositories);
    }

    public function testReadsLinesSeparatedByCarriageReturns(): void
    {
        $list = SubmissionList::fromText("symfony/console\r\nnikic/iter");

        self::assertSame(['symfony/console', 'nikic/iter'], $list->repositories);
    }

    public function testDropsWholeLineComments(): void
    {
        $list = SubmissionList::fromText("# the ones we care about\nsymfony/console");

        self::assertSame(['symfony/console'], $list->repositories);
    }

    public function testDropsCommentsBehindARepository(): void
    {
        $list = SubmissionList::fromText('symfony/console # split off symfony/symfony');

        self::assertSame(['symfony/console'], $list->repositories);
    }

    public function testSubmitsARepositoryNamedTwiceOnlyOnce(): void
    {
        $list = SubmissionList::fromText("symfony/console\nnikic/iter\nsymfony/console");

        self::assertSame(['symfony/console', 'nikic/iter'], $list->repositories);
    }

    public function testTreatsRepositoriesThatDifferInCaseAsTheSameOne(): void
    {
        $list = SubmissionList::fromText("WordPress/WordPress\nwordpress/wordpress");

        self::assertSame(['WordPress/WordPress'], $list->repositories);
    }

    public function testIsEmptyWithoutRepositories(): void
    {
        $list = SubmissionList::fromText("\n# nothing but a comment\n");

        self::assertTrue($list->isEmpty());
        self::assertSame([], $list->repositories);
        self::assertSame(0, $list->count());
    }

    public function testIsEmptyForAnEmptyText(): void
    {
        self::assertTrue(SubmissionList::fromText('')->isEmpty());
    }
}
