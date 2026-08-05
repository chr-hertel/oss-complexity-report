<?php

declare(strict_types=1);

namespace App;

use App\Message\BackfillMetrics;
use App\Message\RefreshRepositories;
use App\Message\ScanForNewReleases;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Message\RedispatchMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule as SymfonySchedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Keeps the report up to date without anyone running a command.
 *
 * Every task is handed over to the `async` transport instead of being handled by the worker that
 * consumes the schedule - they walk every submitted repository and would block the next trigger.
 *
 * It is the only schedule of the application, so it stays the unnamed default one and the worker that
 * triggers it consumes `scheduler_default`.
 */
#[AsSchedule]
final readonly class Schedule implements ScheduleProviderInterface
{
    public function __construct(
        private CacheInterface $cache,
        private LockFactory $lockFactory,
    ) {
    }

    public function getSchedule(): SymfonySchedule
    {
        return (new SymfonySchedule())
            // a deploy restarts the worker - without this, every restart would re-trigger the night
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true)
            // and without this, a second worker on the transport would trigger everything twice
            ->lock($this->lockFactory->createLock('nightly-schedule'))
            ->add(
                RecurringMessage::every('1 day', new RedispatchMessage(new ScanForNewReleases(), 'async'), from: '03:00'),
                // stars decide the order of the whole report, so they are refreshed before the scan
                RecurringMessage::every('1 day', new RedispatchMessage(new RefreshRepositories(), 'async'), from: '02:00'),
                /*
                 * The one task that is not about keeping the report current but about finishing it: the
                 * releases measured before the full phploc output was kept only carry the two numbers
                 * the chart is drawn from. Re-measuring one costs a clone, so this runs all day at a
                 * trickle instead of once a night in bulk - a few repositories an hour, until there is
                 * nothing incomplete left and the run stops finding anything to queue.
                 */
                RecurringMessage::every('1 hour', new RedispatchMessage(new BackfillMetrics(), 'async')),
            );
    }
}
