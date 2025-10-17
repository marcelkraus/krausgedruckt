<?php

namespace App\Entity;

use App\Repository\PortfolioPieceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;
use Symfony\Component\Finder\Finder;

#[ORM\Entity(repositoryClass: PortfolioPieceRepository::class)]
class PortfolioPiece
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sourceTitle = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sourceUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sourceAuthor = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getImages(): ?array
    {
        $result = [];

        try {
            $finder = new Finder();
            $finder
                ->files()
                ->name('*.jpg')
                ->in(sprintf('%s/../../public/images/portfolio-pieces/%d', __DIR__, $this->id));

            foreach ($finder as $file) {
                $result[] = $file->getRelativePathname();
            }
        } catch (DirectoryNotFoundException $e) {
            // Intentionally left blank.
        }

        return $result;
    }

    public function getSourceTitle(): ?string
    {
        return $this->sourceTitle;
    }

    public function setSourceTitle(?string $sourceTitle): static
    {
        $this->sourceTitle = $sourceTitle;

        return $this;
    }

    public function getSourceUrl(): ?string
    {
        return $this->sourceUrl;
    }

    public function setSourceUrl(?string $sourceUrl): static
    {
        $this->sourceUrl = $sourceUrl;

        return $this;
    }

    public function getSourceAuthor(): ?string
    {
        return $this->sourceAuthor;
    }

    public function setSourceAuthor(?string $sourceAuthor): static
    {
        $this->sourceAuthor = $sourceAuthor;

        return $this;
    }
}
