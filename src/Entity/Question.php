<?php

namespace App\Entity;

use \DateTimeImmutable;

class Question
{
    protected bool $isActive;
    protected string $title;
    protected string $content;

    /**
     * @param bool $isActive
     * @param string $title
     * @param string $content
     */
    public function __construct(bool $isActive, string $title, string $content)
    {
        $this->isActive = $isActive;
        $this->title = $title;
        $this->content = $content;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getQuestion(): string
    {
        return $this->title;
    }

    public function getAnswer(): string
    {
        return $this->content;
    }
}
