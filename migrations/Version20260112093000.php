<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260112093000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial migration.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE reference (id BINARY(16) NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, image VARCHAR(255) DEFAULT NULL, updated_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, source_title VARCHAR(255) NOT NULL, source_url VARCHAR(255) NOT NULL, source_author VARCHAR(255) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE reference');
    }
}
