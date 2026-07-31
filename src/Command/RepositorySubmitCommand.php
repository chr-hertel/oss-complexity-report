<?php

declare(strict_types=1);

namespace App\Command;

use App\ComplexityReport\Exception\SubmissionFailed;
use App\ComplexityReport\RepositorySubmitter;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('app:repository:submit', 'Submits GitHub repositories for analysis')]
final readonly class RepositorySubmitCommand
{
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
        array $repositories,
    ): int {
        $io->title('Submitting repositories');

        $failed = false;

        foreach ($repositories as $submitted) {
            try {
                $repository = $this->submitter->submit($submitted);
            } catch (SubmissionFailed $exception) {
                $io->error($exception->getMessage());
                $failed = true;
                continue;
            }

            $io->text(sprintf(
                ' * <options=bold>%s</> (%s stars) queued for analysis',
                $repository->getName(),
                number_format($repository->getStars())
            ));
        }

        if ($failed) {
            return 1;
        }

        $io->success('Done - run app:data:aggregate --pending to analyse them.');

        return 0;
    }
}
