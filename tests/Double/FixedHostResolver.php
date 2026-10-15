<?php

declare(strict_types=1);

namespace App\Tests\Double;

use App\Service\HostResolver;

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
