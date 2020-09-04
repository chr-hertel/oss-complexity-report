<?php

declare(strict_types=1);

namespace App\Entity;

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
     * @var Tag[]&Collection
     *
     * @ORM\OneToMany(targetEntity="Tag", mappedBy="library")
     */
    private $tags;

    public function __construct(string $name, string $repositoryUrl, Project $project)
    {
        $this->name = $name;
        $this->repositoryUrl = $repositoryUrl;
        $this->project = $project;
        $this->tags = new ArrayCollection();
    }
}
