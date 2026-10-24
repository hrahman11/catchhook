<?php

namespace App\Services\Replay;

final readonly class ReplayTarget
{
    public function __construct(
        public string $url,
        public string $host,
        public int $port,
        /** The validated IP the connection is pinned to, or null when the host is used as-is. */
        public ?string $pinnedIp = null,
    ) {}

    /**
     * cURL options that force the connection to the validated IP, so a second
     * DNS lookup can't be pointed somewhere else (DNS rebinding).
     *
     * @return array<int, mixed>
     */
    public function curlOptions(): array
    {
        if ($this->pinnedIp === null) {
            return [];
        }

        $ip = str_contains($this->pinnedIp, ':') ? "[{$this->pinnedIp}]" : $this->pinnedIp;

        return [CURLOPT_RESOLVE => ["{$this->host}:{$this->port}:{$ip}"]];
    }
}
