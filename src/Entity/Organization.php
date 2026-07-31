<?php

declare(strict_types=1);

namespace App\Entity;

use App\ComplexityReport\GitHub\OwnerData;
use App\Repository\OrganizationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * The GitHub account a repository belongs to - an organization like `symfony`, or a user like `nikic`.
 *
 * Organizations are not curated but derived from the repositories that were submitted, so the only thing
 * they carry is what github.com states about them: the login they are addressed by and their avatar. The
 * display name and homepage this used to hold came from optional profile fields and were wrong as often
 * as they were right - the login is what identifies an account.
 */
#[ORM\Entity(repositoryClass: OrganizationRepository::class)]
class Organization
{
    #[ORM\Id, ORM\Column(type: 'integer'), ORM\GeneratedValue]
    private int $id;

    /**
     * @var Collection<int, Repository>
     */
    #[ORM\OneToMany(targetEntity: Repository::class, mappedBy: 'organization')]
    private Collection $repositories;

    public function __construct(
        #[ORM\Column(unique: true)]
        private readonly string $login,
        #[ORM\Column(nullable: true)]
        private ?string $avatarUrl = null,
    ) {
        $this->repositories = new ArrayCollection();
    }

    public static function fromGitHub(OwnerData $owner): self
    {
        return new self($owner->login, $owner->avatarUrl);
    }

    public function update(OwnerData $owner): void
    {
        $this->avatarUrl = $owner->avatarUrl;
    }

    public function getLogin(): string
    {
        return $this->login;
    }

    public function getUrl(): string
    {
        return sprintf('https://github.com/%s', $this->login);
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
}
