<?php

namespace App\Entity;

class Image
{
    protected string $file;
    protected string $alt;

    public function __construct(string $file, string $alt)
    {
        $this->file = $file;
        $this->alt = $alt;
    }

    public function getFile(): string
    {
        return $this->file;
    }

    public function getAlt(): string
    {
        return $this->alt;
    }
}
