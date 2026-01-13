<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260112225111 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add FAQ management.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE faq_entry (id BINARY(16) NOT NULL, question VARCHAR(255) NOT NULL, answer LONGTEXT NOT NULL, is_visible TINYINT DEFAULT 0 NOT NULL, sort_order INT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, PRIMARY KEY (id), INDEX idx_sort_order (sort_order)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE reference CHANGE is_visible is_visible TINYINT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE faq_entry');
        $this->addSql('ALTER TABLE reference CHANGE is_visible is_visible TINYINT DEFAULT 1 NOT NULL');
    }
}
