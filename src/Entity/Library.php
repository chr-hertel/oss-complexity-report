<?php

declare(strict_types=1);

namespace App\Entity;

use App\ComplexityReport\Analysis;
use App\ComplexityReport\GitTag;
use App\ComplexityReport\GraphData;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class Library
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue
     *
     * @psalm-suppress PropertyNotSetInConstructor
     */
    private int $id;

    /**
     * @ORM\Column(unique=true)
     */
    private string $name;

    /**
     * @ORM\Column
     */
    private string $repositoryUrl;

    /**
     * @ORM\ManyToOne(targetEntity="Project", inversedBy="libraries")
     */
    private Project $project;

    /**
     * @var Tag[]|Collection
     * @psalm-var Collection<int, Tag>
     *
     * @ORM\OneToMany(targetEntity="Tag", mappedBy="library", cascade={"persist"})
     * @ORM\OrderBy({"created" = "ASC"})
     */
    private $tags;

    public function __construct(string $name, string $repositoryUrl, Project $project)
    {
        $this->name = $name;
        $this->repositoryUrl = $repositoryUrl;
        $this->project = $project;
        $this->tags = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getRepositoryUrl(): string
    {
        return $this->repositoryUrl;
    }

    public function getProject(): Project
    {
        return $this->project;
    }

    /**
     * @return array<int, Tag>
     */
    public function getTags(): array
    {
        return $this->tags->toArray();
    }

    public function getRepositoryPath(): string
    {
        return sprintf('%s/%s', mb_strtolower($this->getProject()->getName()), mb_strtolower($this->getName()));
    }

    public function addTag(GitTag $tag, Analysis $analysis): void
    {
        $this->tags->add(
            new Tag($tag->getName(), $analysis->getCreated(), $analysis->getLinesOfCode(), $analysis->getAverageComplexity(), $this)
        );
    }

    public function asGraph(): GraphData
    {
        return new GraphData($this);
    }
}
