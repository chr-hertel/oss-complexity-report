<?php

declare(strict_types=1);

namespace App\ComplexityReport;

use App\Entity\Repository;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;

/**
 * There is one working copy per repository, and checking out a tag rewrites it - so whoever touches it
 * takes this lock first, be that an analysis or the cleanup that throws the working copy away.
 */
final readonly class WorkingCopyLock
{
    public function __construct(
        private LockFactory $lockFactory,
    ) {
    }

    public function create(Repository $repository): LockInterface
    {
        return $this->lockFactory->createLock(sprintf('working-copy-%s', $repository->getLocalPath()));
    }
}
