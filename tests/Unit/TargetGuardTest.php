<?php

use App\Services\Replay\HostResolver;
use App\Services\Replay\TargetGuard;
use App\Services\Replay\UnsafeTargetException;

function guardResolvingTo(array $records, bool $allowPrivate = false): TargetGuard
{
    $resolver = new class($records) implements HostResolver
    {
        public function __construct(private array $records) {}

        public function resolve(string $host): array
        {
            return filter_var($host, FILTER_VALIDATE_IP) ? [$host] : ($this->records[$host] ?? []);
        }
    };

    return new TargetGuard($resolver, $allowPrivate);
}

it('accepts public targets and pins the resolved IP', function () {
    $target = guardResolvingTo(['hooks.example.com' => ['93.184.216.34']])->inspect('https://hooks.example.com/stripe');

    expect($target)
        ->host->toBe('hooks.example.com')
        ->port->toBe(443)
        ->pinnedIp->toBe('93.184.216.34')
        ->and($target->curlOptions())->toBe([CURLOPT_RESOLVE => ['hooks.example.com:443:93.184.216.34']]);
});

it('uses an explicit port and brackets pinned IPv6 addresses', function () {
    $target = guardResolvingTo(['v6.example.com' => ['2606:4700::1111']])->inspect('http://v6.example.com:8080/');

    expect($target->curlOptions())->toBe([CURLOPT_RESOLVE => ['v6.example.com:8080:[2606:4700::1111]']]);
});

it('does not pin literal IP targets', function () {
    expect(guardResolvingTo([])->inspect('http://1.1.1.1/')->curlOptions())->toBe([]);
});

it('blocks internal addresses', function (string $url) {
    guardResolvingTo([
        'localhost' => ['127.0.0.1'],
        'internal.example.com' => ['10.1.2.3'],
        'rebind.example.com' => ['93.184.216.34', '192.168.0.10'],
    ])->inspect($url);
})->throws(UnsafeTargetException::class)->with([
    'loopback' => 'http://127.0.0.1:8000/',
    'localhost' => 'http://localhost/',
    'private network' => 'http://192.168.1.1/admin',
    'cloud metadata' => 'http://169.254.169.254/latest/meta-data/',
    'carrier-grade NAT' => 'http://100.64.0.1/',
    'unspecified' => 'http://0.0.0.0/',
    'multicast' => 'http://224.0.0.1/',
    'IPv6 loopback' => 'http://[::1]/',
    'IPv6 unique local' => 'http://[fd12:3456::1]/',
    'IPv4-mapped IPv6' => 'http://[::ffff:127.0.0.1]/',
    'hostname to private IP' => 'https://internal.example.com/',
    'mixed public and private records' => 'https://rebind.example.com/',
]);

it('rejects unsupported or malformed URLs', function (string $url) {
    guardResolvingTo([])->inspect($url);
})->throws(UnsafeTargetException::class)->with([
    'file' => 'file:///etc/passwd',
    'gopher' => 'gopher://example.com/',
    'ftp' => 'ftp://example.com/',
    'no host' => 'http:///path',
    'credentials' => 'http://user:secret@93.184.216.34/',
]);

it('rejects hosts that do not resolve', function () {
    guardResolvingTo([])->inspect('https://does-not-exist.example/');
})->throws(UnsafeTargetException::class, 'Could not resolve host');

it('allows private targets when explicitly enabled', function () {
    $target = guardResolvingTo([], allowPrivate: true)->inspect('http://localhost:3000/webhooks');

    expect($target)
        ->host->toBe('localhost')
        ->port->toBe(3000)
        ->pinnedIp->toBeNull();
});

it('still enforces the scheme when private targets are allowed', function () {
    guardResolvingTo([], allowPrivate: true)->inspect('file:///etc/passwd');
})->throws(UnsafeTargetException::class);
