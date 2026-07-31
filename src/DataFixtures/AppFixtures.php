<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\ComplexityReport\Exception\SubmissionFailed;
use App\ComplexityReport\RepositorySubmitter;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Psr\Log\LoggerInterface;

/**
 * Seeds a fresh installation with a couple of well known repositories - everything else is submitted by users.
 */
final class AppFixtures extends Fixture
{
    private const SEED_REPOSITORIES = [
        'symfony/symfony',
        'laravel/framework',
        'WordPress/WordPress',
        'composer/composer',
        'doctrine/orm',
        'sebastianbergmann/phpunit',
        'laminas/laminas-mvc',
        'thephpleague/flysystem',
        'TYPO3/typo3',
        'Seldaek/monolog',
    ];

    public function __construct(
        private RepositorySubmitter $submitter,
        private LoggerInterface $logger,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        foreach (self::SEED_REPOSITORIES as $repository) {
            try {
                $this->submitter->submit($repository);
            } catch (SubmissionFailed $exception) {
                $this->logger->warning(sprintf('Cannot seed %s: %s', $repository, $exception->getMessage()));
            }
        }

        $manager->flush();
    }
}
