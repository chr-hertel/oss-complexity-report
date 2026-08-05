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
     * $metrics is everything phploc counted for this release, kept as measured. It is `null` for every
     * release measured before the report started keeping it - the two numbers above were all it stored,
     * and the rest is only recoverable by cloning the repository and checking the tag out again, which
     * is what {@see \App\ComplexityReport\MetricsBackfiller} does a few repositories at a time.
     *
     * @param array<string, float|int>|null $metrics
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
        #[ORM\Column(type: 'json', nullable: true)]
        private ?array $metrics = null,
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
     * @return array<string, float|int>|null
     */
    public function getMetrics(): ?array
    {
        return $this->metrics;
    }

    public function hasMetrics(): bool
    {
        return null !== $this->metrics;
    }

    /**
     * Fills in what a release was measured with, for the releases that predate the report keeping it.
     * The two numbers this entity was written from are not touched: they are the same measurement, and
     * the point of the backfill is the part that was thrown away, not a second opinion on the chart.
     *
     * @param array<string, float|int> $metrics
     */
    public function storeMetrics(array $metrics): void
    {
        $this->metrics = $metrics;
    }
}
