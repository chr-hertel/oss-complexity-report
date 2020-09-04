<?php

namespace App\DataFixtures;

use App\Entity\Library;
use App\Entity\Project;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $symfony = $this->loadSymfony();
        array_map([$manager, 'persist'], $symfony);

        $manager->flush();
    }

    private function loadSymfony(): array
    {
        $project = new Project('Symfony', 'https://symfony.com');
        $libraries = [
            new Library('Console', 'https://github.com/symfony/console', $project),
            new Library('EventDispatcher', 'https://github.com/symfony/event-dispatcher', $project),
            new Library('HttpClient', 'https://github.com/symfony/http-client', $project),
        ];

        return [$project, ...$libraries];
    }
}
