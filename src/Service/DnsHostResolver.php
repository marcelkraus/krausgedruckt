<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Resolves a host through the system's name service.
 *
 * Asks for A and AAAA in one call, because a name that answers on both must
 * have both checked: letting the client pick afterwards would hand it an
 * address this side never saw.
 */
final class DnsHostResolver implements HostResolver
{
    public function resolve(string $host): array
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);

        if ($records === false) {
            return [];
        }

        $addresses = [];

        foreach ($records as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;

            if (is_string($address) && $address !== '') {
                $addresses[] = $address;
            }
        }

        return array_values(array_unique($addresses));
    }
}
