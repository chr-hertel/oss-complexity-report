<?php

declare(strict_types=1);

namespace App\Twig;

use App\Repository\ProjectRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class AppExtension extends AbstractExtension
{
    public function __construct(private ProjectRepository $repository)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('all_projects', [$this->repository, 'findWithData']),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('stars', [$this, 'formatStars']),
        ];
    }

    public function formatStars(int $stars): string
    {
        if ($stars < 1000) {
            return (string) $stars;
        }

        return rtrim(rtrim(number_format($stars / 1000, 1, '.', ''), '0'), '.').'k';
    }
}
