<?php

declare(strict_types=1);

namespace App\Command;

use App\ComplexityReport\Exception\SubmissionFailed;
use App\ComplexityReport\RepositorySubmitter;
use App\ComplexityReport\SubmissionList;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('app:repository:submit', 'Submits GitHub repositories for analysis')]
final readonly class RepositorySubmitCommand
{
    /**
     * How long to wait before asking again whether the queue has room.
     */
    private const int WAIT_SECONDS = 15;

    public function __construct(
        private RepositorySubmitter $submitter,
    ) {
    }

    /**
     * @param string[] $repositories
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Argument('One or more repositories, e.g. "wordpress/wordpress" or "https://github.com/symfony/console"')]
        array $repositories = [],
        #[Option('Read repositories from a file as well, one per line - "-" reads them from stdin')]
        ?string $file = null,
        #[Option('Wait for the queue to have room instead of giving up on the rest of the list')]
        bool $wait = false,
    ): int {
        $io->title('Submitting repositories');

        if (null !== $file) {
            $text = $this->read($file);

            if (null === $text) {
                $io->error(sprintf('Cannot read %s.', $file));

                return 1;
            }

            $repositories = [...$repositories, ...SubmissionList::fromText($text)->repositories];
        }

        // arguments go through the same list, so a repository named twice is still submitted once
        $list = SubmissionList::fromText(implode("\n", $repositories));

        if ($list->isEmpty()) {
            $io->error('No repositories to submit - pass them as arguments or with --file.');

            return 1;
        }

        $queued = 0;
        $known = 0;
        $rejected = 0;

        foreach ($list->repositories as $submitted) {
            if ($wait) {
                $this->waitForRoom($io);
            }

            try {
                $submission = $this->submitter->submit($submitted);
            } catch (SubmissionFailed $exception) {
                $io->text(sprintf(' <fg=red>!</> %s - %s', $submitted, $exception->getMessage()));
                ++$rejected;
                continue;
            }

            $submission->queued ? ++$queued : ++$known;

            $io->text(sprintf(
                ' * <options=bold>%s</> (%s stars) %s',
                $submission->repository->getName(),
                number_format($submission->repository->getStars()),
                $submission->queued ? 'queued for analysis' : 'already part of the report'
            ));
        }

        $io->newLine();
        $io->text(sprintf('%d queued, %d already part of the report, %d rejected.', $queued, $known, $rejected));

        if ($queued > 0) {
            $io->success('Done - run messenger:consume async to analyse them.');
        }

        return $rejected > 0 ? 1 : 0;
    }

    /**
     * Blocks until the submitter takes repositories again - a full queue is not a failure, it is a worker
     * that has not caught up yet, so a list longer than the queue is fed in rather than refused.
     */
    private function waitForRoom(SymfonyStyle $io): void
    {
        if ($this->submitter->isAcceptingSubmissions()) {
            return;
        }

        $io->text(' <fg=yellow>~</> queue is full, waiting for the worker to catch up');

        while (!$this->submitter->isAcceptingSubmissions()) {
            sleep(self::WAIT_SECONDS);
        }
    }

    private function read(string $file): ?string
    {
        $text = '-' === $file ? stream_get_contents(\STDIN) : @file_get_contents($file);

        return false === $text ? null : $text;
    }
}
