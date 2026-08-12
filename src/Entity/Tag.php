<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity, ORM\UniqueConstraint(name: 'repo_tag', columns: ['repository_id', 'name'])]
class Tag
{
    #[ORM\Id, ORM\Column(type: 'integer'), ORM\GeneratedValue]
    private int $id;

    /**
     * $metrics is everything phploc counted for this release, kept as measured - the two numbers above
     * are what the report plots of it, not what it stores. Re-reading it is a clone and a checkout away,
     * which is why it is written down once and why every release carries it.
     *
     * @param array<string, float|int> $metrics
     */
    public function __construct(
        #[ORM\Column]
        private string $name,
        #[ORM\Column(type: 'datetime_immutable')]
        private \DateTimeImmutable $created,
        #[ORM\Column(type: 'integer')]
        private int $linesOfCode,
        #[ORM\Column(type: 'float')]
        private float $averageComplexity,
        #[ORM\ManyToOne(targetEntity: Repository::class, inversedBy: 'tags')]
        private Repository $repository,
        #[ORM\Column(type: 'json')]
        private array $metrics,
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

    public function getRepository(): Repository
    {
        return $this->repository;
    }

    /**
     * @return array<string, float|int>
     */
    public function getMetrics(): array
    {
        return $this->metrics;
    }
}
