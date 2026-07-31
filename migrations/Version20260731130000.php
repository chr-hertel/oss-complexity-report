<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Drops everything below 100 stars from the report.
 *
 * The packagist era dataset was curated by dependency counts and carries a long tail nobody reads: forks,
 * abandoned bundles, packages that were popular through one dependent. Popularity is what the report
 * orders by now, so the tail is cut once here - new submissions are not filtered, whoever submits a small
 * repository asked for it.
 *
 * Stars are only filled in by `app:repositories:refresh`, and are 0 for everything until it has run
 * against the GitHub API. Deleting on that would empty the report, so the migration refuses instead.
 */
final class Version20260731130000 extends AbstractMigration
{
    private const MIN_STARS = 100;

    public function getDescription(): string
    {
        return 'Remove repositories below 100 stars from the report';
    }

    public function up(Schema $schema): void
    {
        $starred = (int) $this->connection->fetchOne(
            sprintf('SELECT COUNT(*) FROM repository WHERE stars >= %d', self::MIN_STARS)
        );

        $this->abortIf(
            0 === $starred,
            'No repository has reached 100 stars yet - run app:repositories:refresh first, or this deletes everything.'
        );

        // there is no way back from here, so the deploy log should at least say how much went
        $total = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM repository');
        $this->write(sprintf('Removing %d of %d repositories, keeping the %d with %d stars or more', $total - $starred, $total, $starred, self::MIN_STARS));

        $this->addSql(sprintf(
            'DELETE FROM tag WHERE repository_id IN (SELECT id FROM repository WHERE stars < %d)',
            self::MIN_STARS
        ));
        $this->addSql(sprintf('DELETE FROM repository WHERE stars < %d', self::MIN_STARS));

        // the vendors that are left without a repository have nothing to show anymore
        $this->addSql('DELETE FROM project p WHERE NOT EXISTS (SELECT 1 FROM repository r WHERE r.project_id = p.id)');
    }

    public function down(Schema $schema): void
    {
        // What was measured for those repositories is gone - re-submitting them is the only way back.
        $this->throwIrreversibleMigrationException('Removed repositories cannot be restored, only submitted again.');
    }
}
