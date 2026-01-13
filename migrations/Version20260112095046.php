<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260112095046 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add `isVisible` feature to `reference`.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reference ADD is_visible TINYINT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reference DROP is_visible');
    }
}
