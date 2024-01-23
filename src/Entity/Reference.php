<?php

namespace App\Entity;

use Symfony\Component\Finder\Finder;

class Reference
{
    protected int $id;
    protected string $title;
    protected array $image;
    protected string $description;
    protected ?Source $source;

    /**
     * @param int $id
     * @param string $title
     * @param string $description
     * @param ?Source $source
     */
    public function __construct(int $id, string $title, string $description, ?Source $source = null)
    {
        $this->id = $id;
        $this->title = $title;
        $this->description = $description;
        $this->source = $source;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getImages(): array
    {
        $result = [];

        $finder = new Finder();
        $finder
            ->files()
            ->name('*.jpg')
            ->in(sprintf('%s/../../public/images/references/%d', __DIR__ , $this->id));

        foreach ($finder as $file) {
            $result[] = $file->getRelativePathname();
        }

        return $result;
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