<?php

declare(strict_types=1);

namespace App\ComplexityReport\Trend;

/**
 * What the whole report did to its average complexity within one time frame.
 *
 * `from` and `to` are the mean average complexity over the libraries the window could compare - every
 * library counts once, no matter how many releases or lines of code it carries. `series` is the same
 * figure between those two ends, which is the line the hero draws under it.
 */
final readonly class Trend
{
    /**
     * Below a twentieth of a percent nothing moved - the same threshold the report colours a change by,
     * so a figure printed grey is not narrated as a direction either.
     */
    private const float FLAT = 0.05;

    /**
     * Where "slightly" ends and where "much" begins. The figure is a mean over every library the window
     * carries, so a tenth of it moving is a lot.
     */
    private const float SLIGHT = 2.0;
    private const float LARGE = 10.0;

    /**
     * @param list<TrendPoint> $series the figure over time, oldest sample first
     */
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
        public array $series = [],
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

    /**
     * The figure as a sentence rather than as a number - what the hero opens with, over the line it
     * draws.
     *
     * Complexity is branchiness, so that is the word it is said in: code that "improved" or "regressed"
     * would read a verdict into one metric, which is what the block explaining the report warns against.
     * The degree is coarse on purpose - three steps, so a tenth of a percent is never dressed up.
     */
    public function movement(): string
    {
        $change = abs($this->change);

        if ($change < self::FLAT) {
            return 'held steady';
        }

        $degree = match (true) {
            $change < self::SLIGHT => 'slightly ',
            $change < self::LARGE => '',
            default => 'much ',
        };

        return sprintf('got %s%s branchy', $degree, $this->change < 0 ? 'less' : 'more');
    }
}
