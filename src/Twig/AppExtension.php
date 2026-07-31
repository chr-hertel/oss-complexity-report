<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class AppExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('stars', [$this, 'formatStars']),
            new TwigFilter('compact_number', [$this, 'formatCompactNumber']),
            new TwigFilter('signed_percent', [$this, 'formatSignedPercent']),
            new TwigFilter('trend_tone', [$this, 'trendTone']),
        ];
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
     */
    public function formatSignedPercent(float $value): string
    {
        $sign = match ($this->trendTone($value)) {
            'good' => '↓ −',
            'bad' => '↑ +',
            default => '→ ±',
        };

        return $sign.number_format(abs($value), 1, '.', '').'%';
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
