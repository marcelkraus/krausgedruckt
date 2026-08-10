<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260810160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Split the reference image into a landscape and a portrait variant and rename the multi material printer.';
    }

    public function up(Schema $schema): void
    {
        // The column is renamed only when it is still present, so the migration
        // can be retried without running into an unknown column.
        if ($schema->getTable('reference')->hasColumn('image')) {
            $this->addSql('ALTER TABLE reference CHANGE image image_landscape VARCHAR(255) DEFAULT NULL');
        }

        if ($schema->getTable('reference')->hasColumn('image_portrait') === false) {
            $this->addSql('ALTER TABLE reference ADD image_portrait VARCHAR(255) DEFAULT NULL');
        }

        // The enum is stored as its label, so the renamed printer has to be
        // rewritten or existing references fail to hydrate.
        $this->addSql("UPDATE reference SET printer = 'Prusa MK4S + MMU3' WHERE printer = 'Prusa MK4S+MMU'");
    }

    public function down(Schema $schema): void
    {
        // Rolling back would have to restore the printers that the previous
        // enum never knew, so it refuses instead of pretending.
        $this->throwIrreversibleMigrationException(
            'Two printers were added that the previous enum does not know, so this migration cannot be rolled back.'
        );
    }
}
