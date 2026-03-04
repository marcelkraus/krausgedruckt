<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\String\Slugger\AsciiSlugger;

final class Version20260304133504 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add slug, summary and category to references. Create category table.';
    }

    public function up(Schema $schema): void
    {
        // Create category table
        $this->addSql('CREATE TABLE category (id BINARY(16) NOT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_64C19C1989D9B62 (slug), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');

        // Add slug column to reference
        $this->addSql('ALTER TABLE reference ADD slug VARCHAR(255) NOT NULL DEFAULT \'\'');

        $references = $this->connection->fetchAllAssociative('SELECT id, title FROM reference');
        $slugger = new AsciiSlugger('de');

        foreach ($references as $reference) {
            $slug = strtolower($slugger->slug($reference['title'])->toString());
            $this->addSql('UPDATE reference SET slug = ? WHERE id = ?', [$slug, $reference['id']]);
        }

        $this->addSql('ALTER TABLE reference ALTER slug DROP DEFAULT');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_AEA34913989D9B62 ON reference (slug)');

        // Add summary and category_id to reference
        $this->addSql('ALTER TABLE reference ADD summary VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE reference ADD category_id BINARY(16) DEFAULT NULL');
        $this->addSql('ALTER TABLE reference ADD CONSTRAINT FK_AEA3491312469DE2 FOREIGN KEY (category_id) REFERENCES category (id)');
        $this->addSql('CREATE INDEX IDX_AEA3491312469DE2 ON reference (category_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reference DROP FOREIGN KEY FK_AEA3491312469DE2');
        $this->addSql('DROP INDEX IDX_AEA3491312469DE2 ON reference');
        $this->addSql('ALTER TABLE reference DROP summary');
        $this->addSql('ALTER TABLE reference DROP category_id');
        $this->addSql('DROP TABLE category');
        $this->addSql('DROP INDEX UNIQ_AEA34913989D9B62 ON reference');
        $this->addSql('ALTER TABLE reference DROP slug');
    }
}
