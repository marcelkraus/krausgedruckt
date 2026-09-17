<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260917090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop the references and their categories, which the blog in kongtent replaced.';
    }

    public function up(Schema $schema): void
    {
        // The reference first: it holds the foreign key onto the category.
        $this->addSql('DROP TABLE IF EXISTS reference');
        $this->addSql('DROP TABLE IF EXISTS category');
    }

    public function down(Schema $schema): void
    {
        // The rows are gone with the tables, and an empty schema brought back
        // would be a promise nothing keeps. The data lives in a backup.
        $this->throwIrreversibleMigrationException(
            'The references and categories are backed up outside the project and cannot be restored by a migration.'
        );
    }
}
