<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260818120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the free hashtags of a reference.';
    }

    public function up(Schema $schema): void
    {
        // The column is added only when it is still missing, so the migration
        // can be retried without running into a duplicate column.
        if ($schema->getTable('reference')->hasColumn('hashtags') === false) {
            $this->addSql('ALTER TABLE reference ADD hashtags VARCHAR(255) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        // The column is dropped only when it is present, so the rollback is as
        // repeatable as up() is.
        if ($schema->getTable('reference')->hasColumn('hashtags')) {
            $this->addSql('ALTER TABLE reference DROP hashtags');
        }
    }
}
