<?php

namespace App\Services\Replay;

interface HostResolver
{
    /**
     * Resolve a hostname to every IPv4 and IPv6 address it points to.
     *
     * @return list<string>
     */
    public function resolve(string $host): array;
}
