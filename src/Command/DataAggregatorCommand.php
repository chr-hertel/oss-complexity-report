<?php

declare(strict_types=1);

namespace App\Command;

use App\Message\AnalyseRepository;
use App\Repository\RepositoryRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand('app:data:aggregate', 'Queues submitted repositories for analysis')]
final readonly class DataAggregatorCommand
{
    public function __construct(
        private RepositoryRepository $repositoryRepository,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option('Only queue repositories that were submitted but never analysed')]
        bool $pending = false,
    ): int {
        $io->title('Queueing repositories for analysis');

        $ids = $pending
            ? $this->repositoryRepository->findPendingIds()
            : $this->repositoryRepository->findAllIds();

        foreach ($ids as $id) {
            $this->messageBus->dispatch(new AnalyseRepository($id));
        }

        $io->success(sprintf('Queued %d repositories - run messenger:consume async to work them off.', count($ids)));

        return 0;
    }
}
