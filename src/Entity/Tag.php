<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(uniqueConstraints={
 *     @ORM\UniqueConstraint(name="lib_tag", columns={"library_id", "name"})
 * })
 */
class Tag
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
     * @ORM\Column(type="datetime_immutable")
     */
    private \DateTimeImmutable $created;

    /**
     * @ORM\Column(type="integer")
     */
    private int $linesOfCode;

    /**
     * @ORM\Column(type="float")
     */
    private float $averageComplexity;

    /**
     * @ORM\ManyToOne(targetEntity="Library", inversedBy="tags")
     */
    private Library $library;

    public function __construct(
        string $name,
        \DateTimeImmutable $created,
        int $linesOfCode,
        float $averageComplexity,
        Library $library
    ) {
        $this->name = $name;
        $this->created = $created;
        $this->linesOfCode = $linesOfCode;
        $this->averageComplexity = $averageComplexity;
        $this->library = $library;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCreated(): \DateTimeImmutable
    {
        return $this->created;
    }

    public function getLinesOfCode(): int
    {
        return $this->linesOfCode;
    }

    public function getAverageComplexity(): float
    {
        return $this->averageComplexity;
    }

    public function getLibrary(): Library
    {
        return $this->library;
    }
}
