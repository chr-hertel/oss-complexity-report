<?php

declare(strict_types=1);

namespace App\Command;

use App\ComplexityReport\Exception\SubmissionFailed;
use App\ComplexityReport\RepositoryRemover;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('app:repository:remove', 'Removes repositories and everything analysed for them from the report')]
final readonly class RepositoryRemoveCommand
{
    public function __construct(
        private RepositoryRemover $remover,
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
        $io->title('Removing repositories');

        $failed = false;

        foreach ($repositories as $submitted) {
            try {
                $repository = $this->remover->remove($submitted);
            } catch (SubmissionFailed $exception) {
                $io->error($exception->getMessage());
                $failed = true;
                continue;
            }

            $io->success(sprintf('Removed %s and %d analysed releases', $repository->getName(), count($repository->getTags())));
        }

        $io->note('Working copies are swept by app:repositories:clean.');

        return $failed ? 1 : 0;
    }
}
