<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Turns packagist libraries into submitted GitHub repositories, keeping every analysed release.
 *
 * The packagist package name is not the GitHub slug (doctrine/doctrine-bundle lives in
 * doctrine/DoctrineBundle), so the new name is derived from the repository URL that was already stored.
 * Two consequences are handled below: packages that were renamed on packagist (laravel/airlock ->
 * laravel/sanctum) now collapse onto one repository, and anything not hosted on github.com can no longer
 * be refreshed and is dropped.
 *
 * Stars and descriptions stay empty until `app:repositories:refresh` fills them in from the GitHub API.
 */
final class Version20260731011705 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace libraries with GitHub repositories, preserving analysed tags';
    }

    public function up(Schema $schema): void
    {
        // a vendor's display name comes from GitHub now and is no longer unique
        $this->addSql('DROP INDEX IF EXISTS uniq_2fb3d0ee5e237e06');
        $this->addSql('ALTER TABLE project ADD avatar_url VARCHAR(255) DEFAULT NULL');

        $this->addSql('ALTER TABLE library RENAME TO repository');
        $this->addSql('ALTER SEQUENCE IF EXISTS library_id_seq RENAME TO repository_id_seq');
        $this->addSql('ALTER INDEX IF EXISTS idx_a18098bc166d1f9c RENAME TO idx_5cfe57cd166d1f9c');
        $this->addSql('ALTER TABLE repository RENAME CONSTRAINT fk_a18098bc166d1f9c TO fk_5cfe57cd166d1f9c');

        $this->addSql('ALTER TABLE tag RENAME COLUMN library_id TO repository_id');
        $this->addSql('ALTER INDEX IF EXISTS idx_389b783fe2541d7 RENAME TO idx_389b78350c9d4f7');
        $this->addSql('ALTER INDEX IF EXISTS lib_tag RENAME TO repo_tag');
        $this->addSql('ALTER TABLE tag RENAME CONSTRAINT fk_389b783fe2541d7 TO fk_389b78350c9d4f7');

        // nullable while backfilling, tightened at the end
        $this->addSql('ALTER TABLE repository ADD url VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE repository ADD clone_url VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE repository ADD description TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE repository ADD stars INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE repository ADD submitted TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE repository ADD analysed TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');

        // the report is GitHub only now - anything else cannot be identified or refreshed
        $this->addSql("DELETE FROM tag WHERE repository_id IN (SELECT id FROM repository WHERE repository_url !~* '^https?://(www\\.)?github\\.com/')");
        $this->addSql("DELETE FROM repository WHERE repository_url !~* '^https?://(www\\.)?github\\.com/'");

        $this->addSql('DROP INDEX IF EXISTS uniq_a18098bc5e237e06');
        $this->addSql(<<<'SQL'
            UPDATE repository SET
                name = regexp_replace(regexp_replace(repository_url, '^https?://(www\.)?github\.com/', '', 'i'), '\.git$', '', 'i'),
                url = regexp_replace(repository_url, '\.git$', '', 'i'),
                submitted = NOW(),
                analysed = NOW()
            SQL);
        $this->addSql("UPDATE repository SET clone_url = url || '.git'");

        // renamed packages point at the same repository - keep the oldest row and move its releases over
        $this->addSql(<<<'SQL'
            UPDATE tag SET repository_id = keep.id
            FROM repository dup, (
                SELECT DISTINCT ON (lower(name)) id, lower(name) AS slug FROM repository ORDER BY lower(name), id
            ) keep
            WHERE tag.repository_id = dup.id
                AND lower(dup.name) = keep.slug
                AND dup.id <> keep.id
                AND NOT EXISTS (SELECT 1 FROM tag other WHERE other.repository_id = keep.id AND other.name = tag.name)
            SQL);
        $this->addSql(<<<'SQL'
            DELETE FROM tag WHERE repository_id IN (
                SELECT dup.id FROM repository dup
                WHERE dup.id <> (SELECT MIN(other.id) FROM repository other WHERE lower(other.name) = lower(dup.name))
            )
            SQL);
        $this->addSql(<<<'SQL'
            DELETE FROM repository dup
            WHERE dup.id <> (SELECT MIN(other.id) FROM repository other WHERE lower(other.name) = lower(dup.name))
            SQL);

        $this->addSql('CREATE UNIQUE INDEX UNIQ_5CFE57CD5E237E06 ON repository (name)');
        // the default only existed to backfill the rows above, the entity does not declare one
        $this->addSql('ALTER TABLE repository ALTER stars DROP DEFAULT');
        $this->addSql('ALTER TABLE repository ALTER url SET NOT NULL');
        $this->addSql('ALTER TABLE repository ALTER clone_url SET NOT NULL');
        $this->addSql('ALTER TABLE repository ALTER submitted SET NOT NULL');
        $this->addSql('ALTER TABLE repository DROP repository_url');
    }

    public function down(Schema $schema): void
    {
        // structural rollback only - stars, descriptions, packagist names and merged duplicates are gone
        $this->addSql('ALTER TABLE repository ADD repository_url VARCHAR(255) DEFAULT NULL');
        $this->addSql('UPDATE repository SET repository_url = url');
        $this->addSql('ALTER TABLE repository ALTER repository_url SET NOT NULL');

        $this->addSql('DROP INDEX IF EXISTS uniq_5cfe57cd5e237e06');
        $this->addSql('ALTER TABLE repository DROP analysed');
        $this->addSql('ALTER TABLE repository DROP submitted');
        $this->addSql('ALTER TABLE repository DROP stars');
        $this->addSql('ALTER TABLE repository DROP description');
        $this->addSql('ALTER TABLE repository DROP clone_url');
        $this->addSql('ALTER TABLE repository DROP url');

        $this->addSql('ALTER TABLE tag RENAME CONSTRAINT fk_389b78350c9d4f7 TO fk_389b783fe2541d7');
        $this->addSql('ALTER INDEX IF EXISTS repo_tag RENAME TO lib_tag');
        $this->addSql('ALTER INDEX IF EXISTS idx_389b78350c9d4f7 RENAME TO idx_389b783fe2541d7');
        $this->addSql('ALTER TABLE tag RENAME COLUMN repository_id TO library_id');

        $this->addSql('ALTER TABLE repository RENAME CONSTRAINT fk_5cfe57cd166d1f9c TO fk_a18098bc166d1f9c');
        $this->addSql('ALTER INDEX IF EXISTS idx_5cfe57cd166d1f9c RENAME TO idx_a18098bc166d1f9c');
        $this->addSql('ALTER SEQUENCE IF EXISTS repository_id_seq RENAME TO library_id_seq');
        $this->addSql('ALTER TABLE repository RENAME TO library');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_A18098BC5E237E06 ON library (name)');

        $this->addSql('ALTER TABLE project DROP avatar_url');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2FB3D0EE5E237E06 ON project (name)');
    }
}
