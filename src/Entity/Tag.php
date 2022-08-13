<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity, ORM\UniqueConstraint(name: 'lib_tag', columns: ['library_id', 'name'])]
class Tag
{
    /** @psalm-suppress PropertyNotSetInConstructor */
    #[ORM\Id, ORM\Column(type: 'integer'), ORM\GeneratedValue]
    private int $id;

    public function __construct(
        #[ORM\Column]
        private string $name,
        #[ORM\Column(type: 'datetime_immutable')]
        private \DateTimeImmutable $created,
        #[ORM\Column(type: 'integer')]
        private int $linesOfCode,
        #[ORM\Column(type: 'float')]
        private float $averageComplexity,
        #[ORM\ManyToOne(targetEntity: Library::class, inversedBy: 'tags')]
        private Library $library
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCreated(): \DateTimeImmutable
    {
        return $this->created;
    }

    public function setCreated(\DateTimeImmutable $created): void
    {
        $this->created = $created;
    }

    public function getLinesOfCode(): int
    {
        return $this->linesOfCode;
    }

    public function getAverageComplexity(): float
    {
        return $this->averageComplexity;
    }

    public function getLibrary(): Library
    {
        return $this->library;
    }
}
