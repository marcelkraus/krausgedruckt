<?php

declare(strict_types=1);

namespace App\Asset;

use Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * A deploy rewrites a changed file, so its new mtime gives it a new URL and no browser keeps the old one.
 */
final class FileMtimeVersionStrategy implements VersionStrategyInterface
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/public')]
        private readonly string $publicDir,
    ) {
    }

    public function getVersion(string $path): string
    {
        $mtime = @filemtime($this->publicDir.'/'.ltrim($path, '/'));

        return $mtime === false ? '' : (string) $mtime;
    }

    public function applyVersion(string $path): string
    {
        $version = $this->getVersion($path);

        return $version === '' ? $path : $path.'?v='.$version;
    }
}
