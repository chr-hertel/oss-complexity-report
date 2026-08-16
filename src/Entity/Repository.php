<?php

declare(strict_types=1);

namespace App\Entity;

use App\ComplexityReport\Analysis;
use App\ComplexityReport\GitHub\RepositoryData;
use App\ComplexityReport\GitTag;
use App\ComplexityReport\GraphData;
use App\ComplexityReport\Metric\Metric;
use App\Repository\RepositoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * A repository on github.com that was submitted for analysis.
 */
#[ORM\Entity(repositoryClass: RepositoryRepository::class)]
class Repository
{
    #[ORM\Id, ORM\Column(type: 'integer'), ORM\GeneratedValue]
    private int $id;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $analysed = null;

    /**
     * `EXTRA_LAZY` so asking whether a repository has releases, or how many, is a count rather than a
     * loaded collection: a release carries the phploc measurement it was reduced from, and the pages that
     * ask those two questions never look at a single one of them.
     *
     * @var Collection<int, Tag>
     */
    #[ORM\OneToMany(targetEntity: Tag::class, mappedBy: 'repository', cascade: ['persist'], fetch: 'EXTRA_LAZY'), ORM\OrderBy(['created' => 'ASC'])]
    private Collection $tags;

    public function __construct(
        #[ORM\Column(unique: true)]
        private readonly string $name,
        #[ORM\Column]
        private string $url,
        #[ORM\Column]
        private string $cloneUrl,
        #[ORM\ManyToOne(targetEntity: Organization::class, inversedBy: 'repositories')]
        private readonly Organization $organization,
        #[ORM\Column(type: 'text', nullable: true)]
        private ?string $description = null,
        #[ORM\Column(type: 'integer')]
        private int $stars = 0,
        #[ORM\Column(type: 'datetime_immutable')]
        private readonly \DateTimeImmutable $submitted = new \DateTimeImmutable(),
    ) {
        $this->tags = new ArrayCollection();
    }

    public static function fromGitHub(RepositoryData $data, Organization $organization): self
    {
        return new self(
            (string) $data->identifier,
            $data->url,
            $data->cloneUrl,
            $organization,
            $data->description,
            $data->stars,
        );
    }

    public function update(RepositoryData $data): void
    {
        $this->url = $data->url;
        $this->cloneUrl = $data->cloneUrl;
        $this->description = $data->description;
        $this->stars = $data->stars;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * The repository name without its vendor, e.g. `console` for `symfony/console`.
     */
    public function getShortName(): string
    {
        return substr($this->name, strrpos($this->name, '/') + 1);
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getCloneUrl(): string
    {
        return $this->cloneUrl;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getStars(): int
    {
        return $this->stars;
    }

    public function getOrganization(): Organization
    {
        return $this->organization;
    }

    public function getSubmitted(): \DateTimeImmutable
    {
        return $this->submitted;
    }

    public function getAnalysed(): ?\DateTimeImmutable
    {
        return $this->analysed;
    }

    public function isAnalysed(): bool
    {
        return null !== $this->analysed;
    }

    public function hasData(): bool
    {
        return !$this->tags->isEmpty();
    }

    /**
     * @return array<int, Tag>
     */
    public function getTags(): array
    {
        return $this->tags->toArray();
    }

    /**
     * How many releases were measured so far - a count on the database rather than a loaded collection,
     * which is the whole reason the association is mapped `EXTRA_LAZY`.
     */
    public function getReleaseCount(): int
    {
        return $this->tags->count();
    }

    public function getLocalPath(): string
    {
        return mb_strtolower($this->name);
    }

    public function addTag(GitTag $tag, Analysis $analysis): void
    {
        if ($this->hasTag($tag->getName())) {
            return;
        }

        $this->tags->add(
            new Tag($tag->getName(), $analysis->created, $analysis->linesOfCode, $analysis->averageComplexity, $this, $analysis->metrics)
        );
    }

    public function hasTag(string $name): bool
    {
        return $this->tags->exists(static function (int $key, Tag $tag) use ($name) {
            return $tag->getName() === $name;
        });
    }

    public function markAnalysed(): void
    {
        $this->analysed = new \DateTimeImmutable();
    }

    /**
     * The repository as a line of the chart, drawn in the numbers it was asked for.
     *
     * @param list<Metric> $metrics
     */
    public function asGraph(array $metrics): GraphData
    {
        return new GraphData($this, $metrics);
    }
}
