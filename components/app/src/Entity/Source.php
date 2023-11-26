<?php

namespace App\Entity;
class Source
{
    protected string $title;
    protected string $url;
    protected string $author;

    public function __construct(string $title, string $url, string $author)
    {
        $this->title = $title;
        $this->url = $url;
        $this->author = $author;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getAuthor(): string
    {
        return $this->author;
    }
}