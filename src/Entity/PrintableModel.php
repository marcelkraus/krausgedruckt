<?php

namespace App\Entity;

class PrintableModel
{
    protected string $imageName;
    protected string $modelName;
    protected string $modelUrl;
    protected string $authorName;
    protected string $authorUrl;
    protected string $platform;

    /**
     * @param string $imageName
     * @param string $modelName
     * @param string $modelUrl
     * @param string $authorName
     * @param string $authorUrl
     * @param string $platform
     */
    public function __construct(string $imageName, string $modelName, string $modelUrl, string $authorName, string $authorUrl, string $platform)
    {
        $this->imageName = $imageName;
        $this->modelName = $modelName;
        $this->modelUrl = $modelUrl;
        $this->authorName = $authorName;
        $this->authorUrl = $authorUrl;
        $this->platform = $platform;
    }

    public function getImageName(): string
    {
        return $this->imageName;
    }

    public function setImageName(string $imageName): void
    {
        $this->imageName = $imageName;
    }

    public function getModelName(): string
    {
        return $this->modelName;
    }

    public function setModelName(string $modelName): void
    {
        $this->modelName = $modelName;
    }

    public function getModelUrl(): string
    {
        return $this->modelUrl;
    }

    public function setModelUrl(string $modelUrl): void
    {
        $this->modelUrl = $modelUrl;
    }

    public function getAuthorName(): string
    {
        return $this->authorName;
    }

    public function setAuthorName(string $authorName): void
    {
        $this->authorName = $authorName;
    }

    public function getAuthorUrl(): string
    {
        return $this->authorUrl;
    }

    public function setAuthorUrl(string $authorUrl): void
    {
        $this->authorUrl = $authorUrl;
    }

    public function getPlatform(): string
    {
        return $this->platform;
    }

    public function setPlatform(string $platform): void
    {
        $this->platform = $platform;
    }
}