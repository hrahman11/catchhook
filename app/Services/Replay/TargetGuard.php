<?php

namespace App\Services\Replay;

use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Validates replay destinations to prevent server-side request forgery.
 */
class TargetGuard
{
    /**
     * Ranges blocked in addition to Symfony's private subnet list.
     */
    private const EXTRA_BLOCKED_RANGES = [
        '224.0.0.0/4',        // IPv4 multicast
        '255.255.255.255/32', // Broadcast
        'ff00::/8',           // IPv6 multicast
    ];

    public function __construct(
        private readonly HostResolver $resolver,
        private readonly bool $allowPrivateTargets = false,
    ) {}

    /**
     * @throws UnsafeTargetException
     */
    public function inspect(string $url): ReplayTarget
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = trim($parts['host'] ?? '', '[]');

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new UnsafeTargetException('Only http and https URLs can be replayed to.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new UnsafeTargetException('URLs with embedded credentials are not allowed.');
        }

        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);

        if ($this->allowPrivateTargets) {
            return new ReplayTarget($url, $host, $port);
        }

        $ips = $this->resolver->resolve($host);

        if ($ips === []) {
            throw new UnsafeTargetException("Could not resolve host [{$host}].");
        }

        // Every record must be public: a host that resolves to both a public
        // and a private address could otherwise be used to reach the latter.
        foreach ($ips as $ip) {
            if (! self::isPublicIp($ip)) {
                throw new UnsafeTargetException('Replays to private, loopback or internal network addresses are not allowed.');
            }
        }

        return new ReplayTarget($url, $host, $port, filter_var($host, FILTER_VALIDATE_IP) ? null : $ips[0]);
    }

    public static function isPublicIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false
            && ! IpUtils::isPrivateIp($ip)
            && ! IpUtils::checkIp($ip, self::EXTRA_BLOCKED_RANGES);
    }
}
