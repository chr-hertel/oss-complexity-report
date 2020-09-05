<?php

namespace App\DataFixtures;

use App\Entity\Project;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $projects = [
            new Project('Doctrine', 'https://www.doctrine-project.org/', 'doctrine'),
            new Project('Laravel', 'https://laravel.com/', 'laravel'),
            new Project('PHPUnit', 'https://phpunit.de/', 'phpunit'),
            new Project('Symfony', 'https://symfony.com/', 'symfony'),
            new Project('TYPO3', 'https://typo3.org/', 'typo3'),
        ];
        array_map([$manager, 'persist'], $projects);

        $manager->flush();
    }
}
