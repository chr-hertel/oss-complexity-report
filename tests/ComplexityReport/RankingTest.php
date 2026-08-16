<?php

declare(strict_types=1);

namespace App\Tests\ComplexityReport;

use App\ComplexityReport\RankedRepository;
use App\ComplexityReport\Ranking;
use App\ComplexityReport\ReleaseSummary;
use App\Entity\Organization;
use App\Entity\Repository;
use PHPUnit\Framework\TestCase;

final class RankingTest extends TestCase
{
    /**
     * @dataProvider orders
     *
     * @param list<string> $expected
     */
    public function testItOrdersByItsOwnMeasurement(Ranking $ranking, array $expected): void
    {
        $sorted = $ranking->sort(self::repositories(), 4);

        self::assertSame($expected, array_map(static fn (RankedRepository $r) => $r->repository->getName(), $sorted));
    }

    /**
     * @return iterable<string, array{Ranking, list<string>}>
     */
    public static function orders(): iterable
    {
        yield 'stars' => [Ranking::Stars, ['a/popular', 'a/huge', 'a/hairy', 'a/moving']];
        yield 'complexity' => [Ranking::Complexity, ['a/hairy', 'a/huge', 'a/popular', 'a/moving']];
        yield 'size' => [Ranking::Size, ['a/huge', 'a/popular', 'a/hairy', 'a/moving']];
        // only what grew within the window, the biggest increase first - a drop is not an increase
        yield 'growth' => [Ranking::Growth, ['a/hairy', 'a/popular']];
    }

    public function testItCutsTheListToTheLimit(): void
    {
        self::assertCount(2, Ranking::Stars->sort(self::repositories(), 2));
    }

    public function testItLeavesTheGivenListAlone(): void
    {
        $repositories = self::repositories();
        Ranking::Complexity->sort($repositories, 4);

        self::assertSame('a/popular', $repositories[0]->repository->getName());
    }

    public function testEveryRankingIsDescribed(): void
    {
        foreach (Ranking::all() as $ranking) {
            self::assertNotSame('', $ranking->label());
            self::assertNotSame('', $ranking->title());
            self::assertNotSame('', $ranking->caption());
            self::assertNotSame('', $ranking->legend());
        }
    }

    /**
     * The table is one grid whatever tab is on, so every ranking fills the same three columns - and
     * whatever it sorts by is the one right of the name, so the order the rows are in is readable off
     * the rows themselves.
     */
    public function testEveryRankingLeadsWithWhatItSortsBy(): void
    {
        $leads = [
            Ranking::Stars->value => 'stars',
            Ranking::Complexity->value => 'complexity',
            Ranking::Size->value => 'loc',
            Ranking::Growth->value => 'growth',
        ];

        foreach (Ranking::all() as $ranking) {
            $columns = $ranking->columns();

            self::assertCount(3, $columns, $ranking->value);
            self::assertSame($leads[$ranking->value], $columns[0]['key']);

            foreach ($columns as $column) {
                self::assertNotSame('', $column['label']);
            }
        }
    }

    /**
     * @return list<RankedRepository>
     */
    private static function repositories(): array
    {
        return [
            // name, stars, [loc, complexity] of the first and the latest release
            self::repository('a/popular', 9000, [1000, 2.0], [2000, 2.2]),
            self::repository('a/hairy', 500, [1000, 3.0], [1000, 3.9]),
            self::repository('a/huge', 800, [5000, 2.5], [9000, 2.5]),
            self::repository('a/moving', 100, [1000, 2.0], [900, 1.0]),
        ];
    }

    /**
     * The first release is the baseline of the window as well - so what these repositories did to their
     * complexity, they did within the last twelve months.
     *
     * @param array{int, float} $first
     * @param array{int, float} $latest
     */
    private static function repository(string $name, int $stars, array $first, array $latest): RankedRepository
    {
        $repository = new Repository(
            $name,
            'https://github.com/'.$name,
            'https://github.com/'.$name.'.git',
            new Organization('a'),
            null,
            $stars,
        );

        return RankedRepository::from($repository, new ReleaseSummary(
            1,
            2,
            '1.0',
            new \DateTimeImmutable('-3 years'),
            $first[1],
            $latest[1],
            $latest[0],
            $first[1],
        ));
    }
}
