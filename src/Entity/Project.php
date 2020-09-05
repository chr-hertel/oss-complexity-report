<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ProjectRepository")
 */
class Project
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue
     */
    private int $id;

    /**
     * @ORM\Column(unique=true)
     */
    private string $name;

    /**
     * @ORM\Column
     */
    private string $url;

    /**
     * @var Library[]|Collection
     *
     * @ORM\OneToMany(targetEntity="Library", mappedBy="project")
     */
    private $libraries;

    public function __construct(string $name, string $url)
    {
        $this->name = $name;
        $this->url = $url;
        $this->libraries = new ArrayCollection();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * @return Library[]
     */
    public function getLibraries(): array
    {
        return $this->libraries->toArray();
    }
}
