<?php

namespace App\Services\Replay;

use App\Models\CapturedRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;

/**
 * Re-sends a captured request to another URL, optionally with edits.
 */
class Replayer
{
    /**
     * Headers that describe the original connection rather than the request,
     * so forwarding them would be wrong (or would be rejected by the target).
     */
    public const DROPPED_HEADERS = [
        'host', 'content-length', 'connection', 'keep-alive', 'transfer-encoding',
        'te', 'trailer', 'upgrade', 'proxy-authorization', 'proxy-connection',
    ];

    public const MARKER_HEADER = 'X-Catchhook-Replay';

    public function __construct(
        private readonly Http $http,
        private readonly TargetGuard $guard,
        private readonly int $timeout,
        private readonly int $maxResponseBytes,
    ) {}

    /**
     * @param  list<array{name: string, value: string}>|null  $headers  Replaces the original headers when given.
     * @param  string|null  $body  Replaces the original body when given.
     *
     * @throws UnsafeTargetException
     */
    public function replay(
        CapturedRequest $request,
        string $url,
        ?string $method = null,
        ?array $headers = null,
        ?string $body = null,
    ): ReplayResult {
        $target = $this->guard->inspect($url);

        $outgoing = $this->prepareHeaders($headers === null ? $request->headers : self::groupHeaders($headers));
        $outgoing[self::MARKER_HEADER] = [$request->id];

        $body ??= $request->rawBody();
        $started = hrtime(true);

        try {
            $response = $this->http
                ->withHeaders($outgoing)
                ->withOptions(['curl' => $target->curlOptions()])
                ->withoutRedirecting()
                ->connectTimeout(min(5, $this->timeout))
                ->timeout($this->timeout)
                ->send($method ?? $request->method, $target->url, $body === '' ? [] : ['body' => $body]);
        } catch (ConnectionException $e) {
            return ReplayResult::failed($e->getMessage(), self::elapsed($started));
        }

        return ReplayResult::received(
            status: $response->status(),
            reason: $response->reason(),
            headers: $response->headers(),
            body: $response->body(),
            durationMs: self::elapsed($started),
            maxBytes: $this->maxResponseBytes,
        );
    }

    /**
     * @param  array<string, list<string>>  $headers
     * @return array<string, list<string>>
     */
    private function prepareHeaders(array $headers): array
    {
        return array_filter(
            $headers,
            fn (string $name) => ! in_array(strtolower($name), self::DROPPED_HEADERS, true)
                && strcasecmp($name, self::MARKER_HEADER) !== 0,
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * @param  list<array{name: string, value: string}>  $headers
     * @return array<string, list<string>>
     */
    private static function groupHeaders(array $headers): array
    {
        $grouped = [];

        foreach ($headers as ['name' => $name, 'value' => $value]) {
            $grouped[strtolower(trim($name))][] = $value;
        }

        return $grouped;
    }

    private static function elapsed(int $started): int
    {
        return (int) round((hrtime(true) - $started) / 1_000_000);
    }
}
