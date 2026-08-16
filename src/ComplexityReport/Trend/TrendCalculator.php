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
 * The same two decisions carry {@see self::series()}, which is the figure between its two ends rather
 * than at them - so the line the hero draws starts on `from`, ends on `to`, and is a statement about
 * the same libraries all the way through.
 *
 * The class is pure on purpose: it takes a list of points and the current time and returns value
 * objects, so everything about it can be tested without a database or a clock.
 */
final readonly class TrendCalculator
{
    /**
     * How many steps the line between the two ends of a window is sampled in. The hero draws it 180px
     * tall and a few hundred wide, so this is what it can show rather than what could be computed - and
     * a window carries one sample more than this, since both ends are on it.
     */
    private const int STEPS = 24;

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
            $this->series($points, array_keys($baseline), $start ?? $this->earliest($baseline), $now),
        );
    }

    /**
     * The figure of a window over time: at every sample, the mean over the libraries it compares, each
     * of them standing at the last release it had by then.
     *
     * It is the same set the figure is computed over, so the line ends where the figure does. All time
     * is the exception it is everywhere else: there a library starts at its own first release rather
     * than at a date they share, so the line begins with the oldest of them and the rest joins it as
     * they were first measured - which is the dataset growing as much as it is the code changing, and
     * why the windows with a start are the ones a shape can be read off.
     *
     * @param list<ReleasePoint> $points
     * @param list<int>          $participants
     *
     * @return list<TrendPoint>
     */
    private function series(array $points, array $participants, \DateTimeImmutable $start, \DateTimeImmutable $now): array
    {
        $taking = array_fill_keys($participants, true);

        /** @var array<int, list<ReleasePoint>> $releases */
        $releases = [];

        foreach ($points as $point) {
            if ($point->created > $now || !isset($taking[$point->repository])) {
                continue;
            }

            $releases[$point->repository][] = $point;
        }

        foreach ($releases as $repository => $ordered) {
            usort($ordered, static fn (ReleasePoint $left, ReleasePoint $right) => $left->created <=> $right->created);
            $releases[$repository] = $ordered;
        }

        $span = $now->getTimestamp() - $start->getTimestamp();
        // a window that opened today has no line to draw, only the day it is on
        $steps = $span > 0 ? self::STEPS : 0;

        // the samples ascend and so do the releases, so every library is walked once across all of them
        // rather than once per sample - this runs on every start page the cache does not answer
        $cursors = array_fill_keys(array_keys($releases), 0);
        $standing = [];
        $series = [];

        for ($step = 0; $step <= $steps; ++$step) {
            $at = $start->modify(sprintf('%+d seconds', intdiv($span * $step, max(1, $steps))));

            foreach ($releases as $repository => $ordered) {
                $cursor = $cursors[$repository];

                while (isset($ordered[$cursor]) && $ordered[$cursor]->created <= $at) {
                    $standing[$repository] = $ordered[$cursor]->averageComplexity;
                    ++$cursor;
                }

                $cursors[$repository] = $cursor;
            }

            if ([] !== $standing) {
                $series[] = new TrendPoint($at, round(array_sum($standing) / \count($standing), 2));
            }
        }

        return $series;
    }

    /**
     * Where all time starts: the first release of the library that was measured furthest back.
     *
     * It is only ever asked about a window that compares something - a report without a single library
     * to compare left before this, as a trend that has no data.
     *
     * @param non-empty-array<int, ReleasePoint> $baseline
     */
    private function earliest(array $baseline): \DateTimeImmutable
    {
        $dates = array_map(static fn (ReleasePoint $point) => $point->created, $baseline);

        return min($dates);
    }
}
