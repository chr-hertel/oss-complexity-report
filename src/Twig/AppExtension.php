<?php

declare(strict_types=1);

namespace App\Twig;

use App\ComplexityReport\ComplexityLevel;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class AppExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('stars', [$this, 'formatStars']),
            new TwigFilter('compact_number', [$this, 'formatCompactNumber']),
            new TwigFilter('signed_percent', [$this, 'formatSignedPercent']),
            new TwigFilter('trend_tone', [$this, 'trendTone']),
            new TwigFilter('complexity_level', [$this, 'complexityLevel']),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('complexity_levels', [$this, 'complexityLevels']),
        ];
    }

    /**
     * Where a measured complexity sits on the scale the footer prints.
     */
    public function complexityLevel(float $complexity): ComplexityLevel
    {
        return ComplexityLevel::of($complexity);
    }

    /**
     * @return ComplexityLevel[] the whole scale, in the order it runs
     */
    public function complexityLevels(): array
    {
        return ComplexityLevel::cases();
    }

    public function formatStars(int $stars): string
    {
        if ($stars < 1000) {
            return (string) $stars;
        }

        return rtrim(rtrim(number_format($stars / 1000, 1, '.', ''), '0'), '.').'k';
    }

    /**
     * Lines of code run into the millions - on a card the exact figure is noise.
     */
    public function formatCompactNumber(int $value): string
    {
        if ($value >= 1000000) {
            return number_format($value / 1000000, 1, '.', '').'M';
        }

        if ($value >= 1000) {
            return number_format($value / 1000, 0, '.', '').'k';
        }

        return (string) $value;
    }

    /**
     * A complexity change with its direction spelled out.
     *
     * The arrow is for a figure standing on its own. In a column of them every sign is already lined up
     * under the sign above it and the colour says the direction as well, so the table drops it rather
     * than saying the same thing three times per row.
     */
    public function formatSignedPercent(float $value, bool $arrow = true): string
    {
        $tone = $this->trendTone($value);
        $sign = match ($tone) {
            'good' => '−',
            'bad' => '+',
            default => '±',
        };

        $arrows = ['good' => '↓ ', 'bad' => '↑ ', 'flat' => '→ '];

        return ($arrow ? $arrows[$tone] : '').$sign.number_format(abs($value), 1, '.', '').'%';
    }

    /**
     * Complexity going down is an improvement, going up is a regression - anything below a tenth of a
     * percent is neither.
     */
    public function trendTone(float $value): string
    {
        if (abs($value) < 0.05) {
            return 'flat';
        }

        return $value < 0 ? 'good' : 'bad';
    }
}
