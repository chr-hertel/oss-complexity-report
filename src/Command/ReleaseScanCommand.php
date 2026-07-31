<?php

declare(strict_types=1);

namespace App\Command;

use App\Message\ScanForNewReleases;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * The nightly check, on demand - only repositories that actually released something get analysed.
 */
#[AsCommand('app:releases:scan', 'Looks for releases that are not in the report yet')]
final readonly class ReleaseScanCommand
{
    public function __construct(
        private MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $io->title('Scanning for new releases');

        $this->messageBus->dispatch(new ScanForNewReleases());

        $io->success('Queued - run messenger:consume async to work it off.');

        return 0;
    }
}
