<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Library;
use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Library>
 *
 * @method list<Library> findByName(string|array $name)
 */
final class LibraryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Library::class);
    }

    /**
     * @return list<Library>
     */
    public function findSelected(): array
    {
        return $this->findByName(Project::getMainLibraries());
    }
}
