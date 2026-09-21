<?php

declare(strict_types=1);

namespace App\Tests\Double;

use App\Service\HostResolver;

/**
 * A host resolver that answers from a list instead of a name server.
 *
 * Every test of the fetcher needs one, and the point of some of them is
 * what happens with an address a real lookup would never return.
 */
final class FixedHostResolver implements HostResolver
{
    public const string PUBLIC_ADDRESS = '93.184.216.34';

    /**
     * @param list<string> $addresses
     */
    public function __construct(private readonly array $addresses = [self::PUBLIC_ADDRESS])
    {
    }

    public function resolve(string $host): array
    {
        return $this->addresses;
    }
}
