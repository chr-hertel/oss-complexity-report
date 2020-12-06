<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Expr\Comparison;
use Doctrine\Common\Collections\Selectable;
use Doctrine\ORM\Mapping as ORM;
use DomainException;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ProjectRepository")
 */
class Project
{
    private const MAIN_LIBRARIES = [
        'composer' => 'composer/composer',
        'doctrine' => 'doctrine/orm',
        'laminas' => 'laminas/laminas-mvc',
        'laravel' => 'laravel/framework',
        'league' => 'league/flysystem',
        'magento' => 'magento/core',
        'phpunit' => 'phpunit/phpunit',
        'symfony' => 'symfony/symfony',
        'typo3' => 'typo3/cms-core',
    ];

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
    private string $url;

    /**
     * @ORM\Column(unique=true)
     */
    private string $vendor;

    /**
     * @var Library[]|Collection|Selectable
     * @psalm-var Selectable&Collection<int, Library>
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
        if (!array_key_exists($this->getVendor(), self::MAIN_LIBRARIES)) {
            throw new DomainException(sprintf('Cannot determine main library of project "%s"', $this->getName()));
        }

        $mainLibrary = $this->libraries->matching(
            new Criteria(new Comparison('name', '=', self::MAIN_LIBRARIES[$this->getVendor()]))
        )->first();

        if (false === $mainLibrary) {
            throw new DomainException(sprintf('Cannot load main library of project "%s"', $this->getName()));
        }

        return $mainLibrary;
    }
}
