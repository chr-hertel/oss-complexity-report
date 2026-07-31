<?php

declare(strict_types=1);

namespace App\Tests\ComplexityReport;

use App\ComplexityReport\Analysis;
use App\ComplexityReport\GitTag;
use App\ComplexityReport\Ranking;
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

        self::assertSame($expected, array_map(static fn (Repository $r) => $r->getName(), $sorted));
    }

    /**
     * @return iterable<string, array{Ranking, list<string>}>
     */
    public static function orders(): iterable
    {
        yield 'stars' => [Ranking::Stars, ['a/popular', 'a/huge', 'a/hairy', 'a/moving']];
        yield 'complexity' => [Ranking::Complexity, ['a/hairy', 'a/huge', 'a/popular', 'a/moving']];
        yield 'size' => [Ranking::Size, ['a/huge', 'a/popular', 'a/hairy', 'a/moving']];
        // the biggest change in either direction comes first, so a drop beats a smaller rise
        yield 'evolution' => [Ranking::Evolution, ['a/moving', 'a/hairy', 'a/popular', 'a/huge']];
    }

    public function testItCutsTheListToTheLimit(): void
    {
        self::assertCount(2, Ranking::Stars->sort(self::repositories(), 2));
    }

    public function testItLeavesTheGivenListAlone(): void
    {
        $repositories = self::repositories();
        Ranking::Complexity->sort($repositories, 4);

        self::assertSame('a/popular', $repositories[0]->getName());
    }

    public function testEveryRankingIsDescribed(): void
    {
        foreach (Ranking::all() as $ranking) {
            self::assertNotSame('', $ranking->label());
            self::assertNotSame('', $ranking->title());
            self::assertNotSame('', $ranking->caption());
        }
    }

    /**
     * @return list<Repository>
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
     * @param array{int, float} $first
     * @param array{int, float} $latest
     */
    private static function repository(string $name, int $stars, array $first, array $latest): Repository
    {
        $repository = new Repository(
            $name,
            'https://github.com/'.$name,
            'https://github.com/'.$name.'.git',
            new Organization('a'),
            null,
            $stars,
        );

        $created = new \DateTimeImmutable('2020-01-01');

        foreach (['1.0' => $first, '2.0' => $latest] as $tag => [$linesOfCode, $complexity]) {
            $repository->addTag(new GitTag($tag), new Analysis($linesOfCode, $complexity, $created));
            $created = $created->modify('+1 year');
        }

        return $repository;
    }
}
