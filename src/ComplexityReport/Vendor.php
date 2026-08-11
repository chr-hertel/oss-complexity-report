<?php

declare(strict_types=1);

namespace App\ComplexityReport;

/**
 * A GitHub account as the row closing the start page reads it: the login, and the measured repositories
 * it accounts for.
 *
 * The account itself is not loaded for this - a vendor is a heading over a handful of repository names,
 * and the question "which accounts group more than one measured repository, and which ones are they" is
 * one grouped query rather than every organization, every repository under it, and every release under
 * those. {@see \App\Repository\OrganizationRepository::findVendors()} is where it is asked.
 */
final readonly class Vendor
{
    /**
     * How many measured repositories an account has to group to be worth naming: a single one is a link
     * to that repository under another name, which the rankings above already carry.
     */
    public const int MINIMUM = 2;

    /**
     * @param non-empty-list<string> $repositories the measured repositories, most starred first
     */
    public function __construct(
        public string $login,
        public array $repositories,
    ) {
    }

    public function count(): int
    {
        return count($this->repositories);
    }
}
