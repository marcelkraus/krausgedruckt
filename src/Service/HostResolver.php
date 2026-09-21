<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Turns a host name into the addresses it points at.
 *
 * It exists as an interface so the tests can hand the fetcher an address
 * without asking a name server: the point of those tests is what happens
 * with a private address, and a real lookup would never return one.
 */
interface HostResolver
{
    /**
     * @return list<string> every address the name points at, empty if none
     */
    public function resolve(string $host): array;
}
