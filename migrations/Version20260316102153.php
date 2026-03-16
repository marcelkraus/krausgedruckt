<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260316102153 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add rating_url column to reference table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reference ADD rating_url VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reference DROP rating_url');
    }
}
