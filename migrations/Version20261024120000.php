<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Counts the analyses of a repository that never finished.
 *
 * A worker that runs out of memory dies without acking its message, so the transport hands it back and
 * it dies again - every hour, for as long as nobody looks. The retry strategy cannot count what never
 * returned to it, so the count lives on the repository, where the next delivery can read it.
 */
final class Version20261024120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Count the unfinished analyses of a repository';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE repository ADD unfinished_analyses INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE repository DROP unfinished_analyses');
    }
}
