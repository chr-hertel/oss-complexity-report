<?php

declare(strict_types=1);

namespace App\Command;

use App\ComplexityReport\BackfillProgressLoader;
use App\ComplexityReport\MetricsBackfiller;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * How far the backfill is, and what it would take next.
 *
 * The hourly run says nothing while it works - it logs a line and queues a few repositories, which is
 * the right amount of noise for something that runs all day and the wrong amount for someone asking
 * whether it is getting anywhere. This is that question answered in one place: what is left, in releases
 * and in repositories, and which repositories the coming runs would pick up.
 *
 * It counts what is stored and nothing else. What is queued right now is `messenger:stats`, and a
 * repository being measured at this moment is still counted as missing here - it is, until it is
 * flushed.
 */
#[AsCommand('app:metrics:status', 'Says how far the phploc output backfill has got')]
final readonly class MetricsStatusCommand
{
    public function __construct(
        private BackfillProgressLoader $progressLoader,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option('How many of the repositories in line to name')]
        int $next = MetricsBackfiller::BATCH,
    ): int {
        $io->title('Phploc output backfill');

        $progress = $this->progressLoader->load($next);

        if (0 === $progress->releases) {
            $io->warning('Nothing is measured yet - there is nothing to backfill.');

            return 0;
        }

        $io->listing([
            sprintf(
                '<options=bold>%s</> of <options=bold>%s</> releases carry their phploc output (%s%%)',
                number_format($progress->storedReleases()),
                number_format($progress->releases),
                number_format($progress->share(), 1),
            ),
            sprintf(
                '<options=bold>%s</> of <options=bold>%s</> measured repositories are still incomplete',
                number_format($progress->incompleteRepositories),
                number_format($progress->measuredRepositories),
            ),
        ]);

        if ($progress->isComplete()) {
            $io->success('Every measured release carries its phploc output.');

            return 0;
        }

        // the same order the hourly run takes them in, so this is the queue and not a sample of it
        $io->section('Next in line');
        $io->table(
            ['Repository', 'Releases missing'],
            array_map(
                static fn (array $row) => [$row['name'], number_format($row['missing'])],
                $progress->next,
            ),
        );

        $io->text(sprintf(
            '%s releases left, in %s repositories - %d hourly run(s) of %d to queue them.',
            number_format($progress->missingReleases),
            number_format($progress->incompleteRepositories),
            $progress->runsLeft(MetricsBackfiller::BATCH),
            MetricsBackfiller::BATCH,
        ));

        return 0;
    }
}
