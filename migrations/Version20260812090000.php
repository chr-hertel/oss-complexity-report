<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Makes the phploc measurement part of what a release is.
 *
 * The column was nullable because that was the honest state of a report whose releases had been reduced
 * to two numbers before it started keeping the rest: `null` meant "measured, but not written down". The
 * backfill has re-measured all of them, and an analysis has kept the full output since the day the column
 * was added, so there is no release left that a `null` could describe - and a nullable column keeps every
 * reader asking a question that only has one answer.
 *
 * It refuses rather than dropping what it cannot convert: a release without its output is a release that
 * was measured, and the way to give it one is to delete the row so the next scan finds it missing.
 */
final class Version20260812090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Require the phploc measurement of a release';
    }

    public function up(Schema $schema): void
    {
        $missing = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM tag WHERE metrics IS NULL');

        $this->abortIf(
            $missing > 0,
            sprintf('%d release(s) carry no phploc output - delete those rows so the next scan measures them again.', $missing)
        );

        $this->addSql('ALTER TABLE tag ALTER metrics SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tag ALTER metrics DROP NOT NULL');
    }
}
