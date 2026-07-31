<?php

declare(strict_types=1);

namespace App\ComplexityReport\Trend;

/**
 * Rolls every measured release up into one figure per time frame: how the average complexity of the
 * tracked libraries changed since the window opened.
 *
 * Two decisions make the number honest rather than merely computable:
 *
 * - Only libraries that were already measured when the window opened take part in it. A library that
 *   was submitted last week would otherwise move the five year figure, although nothing about its code
 *   changed - it would be the dataset growing, not the code getting hairier.
 * - Every library counts once, weighted neither by releases nor by size, and it is represented by the
 *   release it stood at when the window opened. A library that has not released since simply carries
 *   its last measurement forward, which is what the chart shows for it as well.
 *
 * The class is pure on purpose: it takes a list of points and the current time and returns value
 * objects, so everything about it can be tested without a database or a clock.
 */
final readonly class TrendCalculator
{
    /**
     * One trend per window, in the order {@see TrendWindow::all()} declares them.
     *
     * @param list<ReleasePoint> $points
     *
     * @return list<Trend>
     */
    public function calculate(array $points, \DateTimeImmutable $now): array
    {
        return array_map(fn (TrendWindow $window) => $this->forWindow($points, $window, $now), TrendWindow::all());
    }

    /**
     * @param list<ReleasePoint> $points
     */
    public function forWindow(array $points, TrendWindow $window, \DateTimeImmutable $now): Trend
    {
        $start = $window->startedAt($now);

        /** @var array<int, ReleasePoint> $baseline */
        $baseline = [];
        /** @var array<int, ReleasePoint> $current */
        $current = [];

        foreach ($points as $point) {
            // a release dated in the future is a lie the git history tells - it cannot be measured yet
            if ($point->created > $now) {
                continue;
            }

            $repository = $point->repository;

            if (!isset($current[$repository]) || $point->created > $current[$repository]->created) {
                $current[$repository] = $point;
            }

            if (null === $start) {
                // all time: the oldest release of the library is where it started
                if (!isset($baseline[$repository]) || $point->created < $baseline[$repository]->created) {
                    $baseline[$repository] = $point;
                }
            } elseif ($point->created <= $start
                && (!isset($baseline[$repository]) || $point->created > $baseline[$repository]->created)) {
                // otherwise: the release the library stood at when the window opened
                $baseline[$repository] = $point;
            }
        }

        $from = [];
        $to = [];

        foreach ($baseline as $repository => $point) {
            $from[] = $point->averageComplexity;
            $to[] = $current[$repository]->averageComplexity;
        }

        if ([] === $from) {
            return Trend::withoutData($window);
        }

        $fromMean = array_sum($from) / \count($from);
        $toMean = array_sum($to) / \count($to);

        if (0.0 === $fromMean) {
            return Trend::withoutData($window);
        }

        return new Trend(
            $window,
            round($fromMean, 2),
            round($toMean, 2),
            round((($toMean - $fromMean) / $fromMean) * 100, 1),
            \count($from),
            $start,
        );
    }
}
