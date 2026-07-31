<?php

declare(strict_types=1);

namespace App\Tests\ComplexityReport\GitHub;

use App\ComplexityReport\Exception\SubmissionFailed;
use App\ComplexityReport\GitHub\RepositoryIdentifier;
use PHPUnit\Framework\TestCase;

final class RepositoryIdentifierTest extends TestCase
{
    /**
     * @dataProvider provideValidInput
     */
    public function testItReadsTheRepositoryFromInput(string $input, string $expected): void
    {
        self::assertSame($expected, (string) RepositoryIdentifier::fromInput($input));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideValidInput(): iterable
    {
        yield 'vendor and repository' => ['symfony/console', 'symfony/console'];
        yield 'surrounded by spaces' => ['  symfony/console  ', 'symfony/console'];
        yield 'https url' => ['https://github.com/symfony/console', 'symfony/console'];
        yield 'http url' => ['http://github.com/symfony/console', 'symfony/console'];
        yield 'url with www' => ['https://www.github.com/symfony/console', 'symfony/console'];
        yield 'url without scheme' => ['github.com/symfony/console', 'symfony/console'];
        yield 'url with trailing slash' => ['https://github.com/symfony/console/', 'symfony/console'];
        yield 'clone url' => ['https://github.com/symfony/console.git', 'symfony/console'];
        yield 'ssh url' => ['git@github.com:symfony/console.git', 'symfony/console'];
        yield 'deep link' => ['https://github.com/symfony/console/tree/6.3', 'symfony/console'];
        yield 'dots and dashes' => ['WordPress/WordPress-Coding-Standards', 'WordPress/WordPress-Coding-Standards'];
    }

    /**
     * @dataProvider provideInvalidInput
     */
    public function testItRejectsInvalidInput(string $input): void
    {
        $this->expectException(SubmissionFailed::class);

        RepositoryIdentifier::fromInput($input);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidInput(): iterable
    {
        yield 'empty' => [''];
        yield 'vendor only' => ['symfony'];
        yield 'vendor only as url' => ['https://github.com/symfony'];
        yield 'missing repository' => ['symfony/'];
        yield 'another host' => ['https://gitlab.com/inkscape'];
        yield 'invalid characters' => ['symfony/console;rm -rf'];
    }
}
