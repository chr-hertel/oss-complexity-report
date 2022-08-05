<?php

namespace App\DataFixtures;

use App\Entity\Project;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

final class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $projects = [
            new Project('Composer', 'https://www.getcomposer.org/', 'composer'),
            new Project('Doctrine', 'https://www.doctrine-project.org/', 'doctrine'),
            new Project('Laminas', 'https://getlaminas.org/', 'laminas'),
            new Project('Laravel', 'https://laravel.com/', 'laravel'),
            new Project('PHPUnit', 'https://phpunit.de/', 'phpunit'),
            new Project('Symfony', 'https://symfony.com/', 'symfony'),
            new Project('The PHP League', 'https://thephpleague.com/', 'league'),
            new Project('TYPO3', 'https://typo3.org/', 'typo3'),
        ];
        array_map([$manager, 'persist'], $projects);

        $manager->flush();
    }
}
