<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Keeps what phploc measured, next to the two numbers the report is drawn from.
 *
 * A release used to be reduced to its lines of code and its average complexity the moment it was
 * analysed - everything else phploc counted was discarded, although measuring it again means cloning the
 * repository and checking the tag out a second time. The column is nullable because that is the honest
 * state of every release measured until now: nothing was kept, and only a re-measurement can fill it in.
 */
final class Version20260805200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store the full phploc measurement of a release';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tag ADD metrics JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tag DROP metrics');
    }
}
