<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class Source
{
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    protected ?string $title = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    protected ?string $url = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    protected ?string $author = null;

    public function __construct(?string $title = null, ?string $url = null, ?string $author = null)
    {
        $this->title = $title;
        $this->url = $url;
        $this->author = $author;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): self
    {
        $this->url = $url;
        return $this;
    }

    public function getAuthor(): ?string
    {
        return $this->author;
    }

    public function setAuthor(?string $author): self
    {
        $this->author = $author;
        return $this;
    }
}
