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
    /**
     * How far back {@see self::getRecentEvolution()} looks - the twelve months the report calls recent.
     */
    private const string RECENT = '-12 months';

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
     * The oldest analysed release - `null` while nothing was measured yet.
     */
    public function getFirstTag(): ?Tag
    {
        return $this->tags->first() ?: null;
    }

    /**
     * The most recent analysed release - `null` while nothing was measured yet.
     */
    public function getLatestTag(): ?Tag
    {
        return $this->tags->last() ?: null;
    }

    public function getReleaseCount(): int
    {
        return $this->tags->count();
    }

    /**
     * Average cyclomatic complexity of the latest analysed release - the figure the chart ends on.
     */
    public function getComplexity(): float
    {
        return $this->getLatestTag()?->getAverageComplexity() ?? 0.0;
    }

    /**
     * Lines of code of the latest analysed release.
     */
    public function getLinesOfCode(): int
    {
        return $this->getLatestTag()?->getLinesOfCode() ?? 0;
    }

    /**
     * How the average complexity changed between the first and the latest analysed release, in percent.
     * Negative means the codebase got simpler.
     */
    public function getEvolution(): float
    {
        return self::change($this->getFirstTag()?->getAverageComplexity() ?? 0.0, $this->getComplexity());
    }

    /**
     * The same figure for the last twelve months - what the "Latest Increases" ranking is ordered by,
     * over the window the hero rolls the whole report up into.
     */
    public function getRecentEvolution(): float
    {
        return $this->getEvolutionSince(new \DateTimeImmutable(self::RECENT));
    }

    /**
     * How the average complexity changed since a point in time, in percent.
     *
     * The repository is compared against the release it stood at back then, which is the rule the hero
     * trend follows as well. Two cases have nothing to say and are 0.0 rather than a made up number: a
     * repository that was not measured yet when the window opened, and one that has not released since -
     * there the baseline is the latest release, so nothing changed within the window.
     */
    public function getEvolutionSince(\DateTimeImmutable $since): float
    {
        $baseline = null;

        foreach ($this->tags as $tag) {
            if ($tag->getCreated() <= $since && (null === $baseline || $tag->getCreated() > $baseline->getCreated())) {
                $baseline = $tag;
            }
        }

        return self::change($baseline?->getAverageComplexity() ?? 0.0, $this->getComplexity());
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

    /**
     * Change between two average complexities, in percent - a release without a single class has no
     * average to compare against, so it is no change rather than a division by zero.
     */
    private static function change(float $from, float $to): float
    {
        if (0.0 === $from) {
            return 0.0;
        }

        return round((($to - $from) / $from) * 100, 1);
    }
}
