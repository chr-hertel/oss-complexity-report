<?php

declare(strict_types=1);

namespace App\Entity;

use App\ComplexityReport\Analysis;
use App\ComplexityReport\Git\Tag as GitTag;
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
     */
    private int $id;

    /**
     * @ORM\Column
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
     *
     * @ORM\OneToMany(targetEntity="Tag", mappedBy="library", cascade={"persist"})
     */
    private $tags;

    public function __construct(string $name, string $repositoryUrl, Project $project)
    {
        $this->name = $name;
        $this->repositoryUrl = $repositoryUrl;
        $this->project = $project;
        $this->tags = new ArrayCollection();
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
     * @return Tag[]
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
}
