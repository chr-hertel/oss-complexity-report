<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Turns the curated projects into the GitHub accounts the repositories actually belong to.
 *
 * A project used to be a packagist vendor with a display name and a homepage next to it, both written by
 * hand and never corrected since. Neither survived the move to github.com: `league` is a stranger to the
 * repositories of `thephpleague`, `phpunit` to those of `sebastianbergmann`, and `symfony` was told to
 * speak for `hostnet/symfony1`. What is left is what github.com states - the login an account is addressed
 * by, which is also the first half of every repository name in the report.
 *
 * So the rows are not renamed but re-derived: every repository is filed under the owner of its own name,
 * accounts nobody owns anything under are dropped, and the ones that are missing are created. They come
 * without an avatar until `app:repositories:refresh` has run against the GitHub API.
 */
final class Version20260731140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace the curated projects with the GitHub accounts that own the repositories';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project RENAME TO organization');
        $this->addSql('ALTER SEQUENCE IF EXISTS project_id_seq RENAME TO organization_id_seq');
        $this->addSql('ALTER TABLE organization RENAME COLUMN vendor TO login');
        $this->addSql('ALTER INDEX IF EXISTS uniq_2fb3d0eef52233f6 RENAME TO uniq_c1ee637caa08cb10');

        // the display name and the homepage were the curated half of a project - nothing reads them anymore
        $this->addSql('ALTER TABLE organization DROP COLUMN name');
        $this->addSql('ALTER TABLE organization DROP COLUMN url');

        $this->addSql('ALTER TABLE repository RENAME COLUMN project_id TO organization_id');
        $this->addSql('ALTER INDEX IF EXISTS idx_5cfe57cd166d1f9c RENAME TO idx_5cfe57cd32c8a3de');
        $this->addSql('ALTER TABLE repository RENAME CONSTRAINT fk_5cfe57cd166d1f9c TO fk_5cfe57cd32c8a3de');

        // the accounts that own something but were never an organization of their own, e.g. thephpleague.
        // Logins are unique regardless of case on github.com, so a second spelling is not a second account.
        $this->addSql(<<<'SQL'
            INSERT INTO organization (login)
            SELECT owner FROM (
                SELECT DISTINCT ON (lower(split_part(name, '/', 1))) split_part(name, '/', 1) AS owner
                FROM repository
                ORDER BY lower(split_part(name, '/', 1)), split_part(name, '/', 1)
            ) owned
            WHERE NOT EXISTS (SELECT 1 FROM organization o WHERE lower(o.login) = lower(owned.owner))
            SQL);

        $this->addSql(<<<'SQL'
            UPDATE repository r SET organization_id = o.id
            FROM organization o
            WHERE lower(o.login) = lower(split_part(r.name, '/', 1))
            SQL);

        // whatever is left over is a vendor of the packagist era that owns nothing under its own name
        $this->addSql('DELETE FROM organization o WHERE NOT EXISTS (SELECT 1 FROM repository r WHERE r.organization_id = o.id)');
    }

    public function down(Schema $schema): void
    {
        // The vendors, display names and homepages this replaces were curated by hand and are not
        // derivable from anything the report still holds.
        $this->throwIrreversibleMigrationException('The curated projects cannot be derived from GitHub accounts.');
    }
}
