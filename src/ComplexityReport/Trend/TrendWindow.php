<?php

declare(strict_types=1);

namespace App\ComplexityReport\Trend;

/**
 * The time frames the hero rolls the whole report up into.
 *
 * A window is a point in the past the dataset is compared against - except for all time, which has no
 * fixed start: there every library is compared against its own first measured release.
 */
enum TrendWindow: string
{
    case YearToDate = 'ytd';
    case TwelveMonths = '12m';
    case FiveYears = '5y';
    case AllTime = 'all';

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        return self::cases();
    }

    /**
     * The label on the switch in the hero - four of them share one row, so they are as short as the
     * mono type they are set in. {@see self::title()} spells them out.
     */
    public function label(): string
    {
        return match ($this) {
            self::YearToDate => 'YTD',
            self::TwelveMonths => '12M',
            self::FiveYears => '5Y',
            self::AllTime => 'All',
        };
    }

    public function title(): string
    {
        return match ($this) {
            self::YearToDate => 'Year to date',
            self::TwelveMonths => 'Last 12 months',
            self::FiveYears => 'Last 5 years',
            self::AllTime => 'All time',
        };
    }

    /**
     * When the window opens - `null` for all time, which starts at the first release of every library
     * rather than at a date they all share.
     */
    public function startedAt(\DateTimeImmutable $now): ?\DateTimeImmutable
    {
        return match ($this) {
            self::YearToDate => $now->setDate((int) $now->format('Y'), 1, 1)->setTime(0, 0),
            self::TwelveMonths => $now->sub(new \DateInterval('P1Y')),
            self::FiveYears => $now->sub(new \DateInterval('P5Y')),
            self::AllTime => null,
        };
    }
}
