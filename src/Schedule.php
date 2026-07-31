<?php

declare(strict_types=1);

namespace App;

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
 * Both tasks are handed over to the `async` transport instead of being handled by the worker that
 * consumes the schedule - they walk every submitted repository and would block the next trigger.
 */
#[AsSchedule('nightly')]
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
            );
    }
}
