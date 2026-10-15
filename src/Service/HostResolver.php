<?php

declare(strict_types=1);

namespace App\Service;

/**
 * An interface, so tests can hand the fetcher a private address.
 */
interface HostResolver
{
    /**
     * @return list<string> every address the name points at, empty if none
     */
    public function resolve(string $host): array;
}
