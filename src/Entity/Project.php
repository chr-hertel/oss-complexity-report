<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class Project
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue
     */
    private $id;

    /**
     * @ORM\Column(unique=true)
     */
    private $name;

    /**
     * @ORM\Column
     */
    private $url;

    /**
     * @var Library[]&Collection
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
}
