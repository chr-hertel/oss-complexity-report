<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Expr\Comparison;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ProjectRepository")
 */
class Project
{
    private const MAIN_LIBRARIES = [
        'doctrine' => 'doctrine/orm',
        'laravel' => 'laravel/framework',
        'phpunit' => 'phpunit/phpunit',
        'symfony' => 'symfony/symfony',
        'typo3' => 'typo3/cms-core',
    ];

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
     * @ORM\Column(unique=true)
     */
    private string $vendor;

    /**
     * @var Library[]|Collection
     *
     * @ORM\OneToMany(targetEntity="Library", mappedBy="project")
     */
    private $libraries;

    public function __construct(string $name, string $url, string $vendor)
    {
        $this->name = $name;
        $this->url = $url;
        $this->vendor = $vendor;
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

    public function getVendor(): string
    {
        return $this->vendor;
    }

    /**
     * @return Library[]
     */
    public function getLibraries(): array
    {
        return $this->libraries->toArray();
    }

    public function getMainLibrary(): Library
    {
        return $this->libraries->matching(
            new Criteria(new Comparison('name', '=', self::MAIN_LIBRARIES[$this->getVendor()]))
        )->first();
    }
}
