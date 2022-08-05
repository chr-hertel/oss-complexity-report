<?php

declare(strict_types=1);

namespace App\Twig;

use App\Repository\ProjectRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AppExtension extends AbstractExtension
{
    public function __construct(private ProjectRepository $repository)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('all_projects', [$this->repository, 'findAll']),
        ];
    }
}
