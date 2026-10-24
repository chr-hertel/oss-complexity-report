<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Organization;
use App\Entity\Repository;
use PHPUnit\Framework\TestCase;

/**
 * A worker that runs out of memory never tells messenger, so whether a repository keeps killing its
 * analyses is counted on the repository - started, and only cleared by one that finished.
 */
final class RepositoryTest extends TestCase
{
    public function testItIsNotStuckBeforeItsAnalysesKeepDying(): void
    {
        $repository = self::repository();

        for ($i = 1; $i < Repository::MAX_UNFINISHED_ANALYSES; ++$i) {
            $repository->startAnalysis();
        }

        self::assertFalse($repository->isStuck());

        $repository->startAnalysis();

        self::assertTrue($repository->isStuck());
    }

    public function testAFinishedAnalysisClearsTheCount(): void
    {
        $repository = self::stuck();

        $repository->startAnalysis();
        $repository->markAnalysed();

        self::assertFalse($repository->isStuck());
    }

    public function testRetryingGivesItToTheWorkersAgain(): void
    {
        $repository = self::stuck();

        $repository->retryAnalysis();

        self::assertFalse($repository->isStuck());
    }

    private static function stuck(): Repository
    {
        $repository = self::repository();

        for ($i = 0; $i < Repository::MAX_UNFINISHED_ANALYSES; ++$i) {
            $repository->startAnalysis();
        }

        return $repository;
    }

    private static function repository(): Repository
    {
        return new Repository('moodle/moodle', 'https://github.com/moodle/moodle', 'https://github.com/moodle/moodle.git', new Organization('moodle'));
    }
}
