<?php

declare(strict_types=1);

namespace App\Entity;

use App\ComplexityReport\GitHub\OwnerData;
use App\Repository\ProjectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * A GitHub owner - projects are not curated anymore but derived from the repositories that were submitted.
 */
#[ORM\Entity(repositoryClass: ProjectRepository::class)]
class Project
{
    #[ORM\Id, ORM\Column(type: 'integer'), ORM\GeneratedValue]
    private int $id;

    /**
     * @var Collection<int, Repository>
     */
    #[ORM\OneToMany(targetEntity: Repository::class, mappedBy: 'project')]
    private Collection $repositories;

    public function __construct(
        #[ORM\Column(unique: true)]
        private readonly string $vendor,
        #[ORM\Column]
        private string $name,
        #[ORM\Column]
        private string $url,
        #[ORM\Column(nullable: true)]
        private ?string $avatarUrl = null,
    ) {
        $this->repositories = new ArrayCollection();
    }

    public static function fromGitHub(OwnerData $owner): self
    {
        return new self($owner->login, $owner->name, $owner->url, $owner->avatarUrl);
    }

    public function update(OwnerData $owner): void
    {
        $this->name = $owner->name;
        $this->url = $owner->url;
        $this->avatarUrl = $owner->avatarUrl;
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

    public function getAvatarUrl(): ?string
    {
        return $this->avatarUrl;
    }

    /**
     * @return list<Repository>
     */
    public function getRepositories(): array
    {
        $repositories = $this->repositories->toArray();

        // usort reindexes, so the result is a list
        usort($repositories, static function (Repository $left, Repository $right) {
            return $right->getStars() <=> $left->getStars();
        });

        return $repositories;
    }

    /**
     * @return list<Repository>
     */
    public function getAnalysedRepositories(): array
    {
        $analysed = [];

        foreach ($this->getRepositories() as $repository) {
            if ($repository->hasData()) {
                $analysed[] = $repository;
            }
        }

        return $analysed;
    }

    public function getStars(): int
    {
        return array_sum(array_map(static function (Repository $repository) {
            return $repository->getStars();
        }, $this->repositories->toArray()));
    }

    /**
     * The most popular repository of a vendor is the one preselected in its chart.
     */
    public function getMainRepository(): Repository
    {
        $repositories = $this->getAnalysedRepositories() ?: $this->getRepositories();

        if ([] === $repositories) {
            throw new \DomainException(sprintf('Project "%s" does not have any repository.', $this->name));
        }

        return $repositories[0];
    }
}
