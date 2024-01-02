<?php

namespace App\Entity;

use \DateTimeImmutable;

class Reference
{
    protected int $id;
    protected DateTimeImmutable $date;
    protected string $title;
    protected array $image;
    protected ?string $client;

    protected string $description;
    protected ?Source $source;

    /**
     * @param int $id
     * @param string $date
     * @param string $title
     * @param array $images
     * @param ?string $client
     * @param string $description
     * @param ?Source $source
     */
    public function __construct(int $id, string $date, string $title, array $images, string $client = null, string $description, ?Source $source = null)
    {
        $this->id = $id;
        $this->date = DateTimeImmutable::createFromFormat("Y-m-d", $date);
        $this->title = $title;
        $this->images = $images;
        $this->client = $client;
        $this->description = $description;
        $this->source = $source;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getDate(): DateTimeImmutable
    {
        return $this->date;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getImages(): array
    {
        return $this->images;
    }

    public function getClient(): ?string
    {
        return $this->client;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getSource(): ?Source
    {
        return $this->source;
    }
}