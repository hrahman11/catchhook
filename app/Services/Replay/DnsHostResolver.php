<?php

namespace App\Services\Replay;

class DnsHostResolver implements HostResolver
{
    public function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $ipv4 = gethostbynamel($host) ?: [];
        $ipv6 = array_column(@dns_get_record($host, DNS_AAAA) ?: [], 'ipv6');

        return array_values(array_unique([...$ipv4, ...$ipv6]));
    }
}
