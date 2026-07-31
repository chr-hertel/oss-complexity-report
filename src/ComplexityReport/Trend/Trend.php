<?php

declare(strict_types=1);

namespace App\ComplexityReport\Trend;

/**
 * What the whole report did to its average complexity within one time frame.
 *
 * `from` and `to` are the mean average complexity over the libraries the window could compare - every
 * library counts once, no matter how many releases or lines of code it carries.
 */
final readonly class Trend
{
    public function __construct(
        public TrendWindow $window,
        public float $from,
        public float $to,
        /**
         * Change between the two, in percent - negative means the code got simpler.
         */
        public float $change,
        /**
         * How many libraries the figure is an average of.
         */
        public int $repositoryCount,
        /**
         * The date the window opened - `null` for all time, where every library starts at its own first
         * release.
         */
        public ?\DateTimeImmutable $since = null,
    ) {
    }

    /**
     * A window nothing can be said about: no library was measured before it opened.
     */
    public static function withoutData(TrendWindow $window): self
    {
        return new self($window, 0.0, 0.0, 0.0, 0);
    }

    public function hasData(): bool
    {
        return $this->repositoryCount > 0;
    }
}
