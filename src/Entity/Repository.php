<?php

declare(strict_types=1);

namespace App\Entity;

use App\ComplexityReport\Analysis;
use App\ComplexityReport\GitHub\RepositoryData;
use App\ComplexityReport\GitTag;
use App\ComplexityReport\GraphData;
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
     * @var Collection<int, Tag>
     */
    #[ORM\OneToMany(targetEntity: Tag::class, mappedBy: 'repository', cascade: ['persist']), ORM\OrderBy(['created' => 'ASC'])]
    private Collection $tags;

    public function __construct(
        #[ORM\Column(unique: true)]
        private readonly string $name,
        #[ORM\Column]
        private string $url,
        #[ORM\Column]
        private string $cloneUrl,
        #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'repositories')]
        private readonly Project $project,
        #[ORM\Column(type: 'text', nullable: true)]
        private ?string $description = null,
        #[ORM\Column(type: 'integer')]
        private int $stars = 0,
        #[ORM\Column(type: 'datetime_immutable')]
        private readonly \DateTimeImmutable $submitted = new \DateTimeImmutable(),
    ) {
        $this->tags = new ArrayCollection();
    }

    public static function fromGitHub(RepositoryData $data, Project $project): self
    {
        return new self(
            (string) $data->identifier,
            $data->url,
            $data->cloneUrl,
            $project,
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

    public function getProject(): Project
    {
        return $this->project;
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
            new Tag($tag->getName(), $analysis->created, $analysis->linesOfCode, $analysis->averageComplexity, $this)
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

    public function asGraph(): GraphData
    {
        return new GraphData($this);
    }
}
