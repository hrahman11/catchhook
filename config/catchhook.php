<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Bin Lifetime
    |--------------------------------------------------------------------------
    |
    | Number of days a bin stays active after it is created. Requests sent to
    | an expired bin are rejected, and expired bins are pruned periodically.
    |
    */

    'bin_ttl_days' => (int) env('CATCHHOOK_BIN_TTL_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Capture Limits
    |--------------------------------------------------------------------------
    |
    | Capture URLs are public, so these keep a single bin or client from
    | exhausting storage or workers. Bins keep only their most recent
    | requests; older ones are discarded as new requests arrive.
    |
    */

    'limits' => [
        'max_body_kb' => (int) env('CATCHHOOK_MAX_BODY_KB', 512),
        'max_requests_per_bin' => (int) env('CATCHHOOK_MAX_REQUESTS_PER_BIN', 500),
        'captures_per_minute_per_bin' => (int) env('CATCHHOOK_CAPTURES_PER_MINUTE_PER_BIN', 120),
        'captures_per_minute_per_ip' => (int) env('CATCHHOOK_CAPTURES_PER_MINUTE_PER_IP', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Responses
    |--------------------------------------------------------------------------
    |
    | Bins can return a custom status, headers and body, optionally after an
    | artificial delay for testing sender timeouts and retries. The delay is
    | capped because it holds a PHP worker for its whole duration.
    |
    */

    'response' => [
        'max_delay_ms' => (int) env('CATCHHOOK_MAX_RESPONSE_DELAY_MS', 10_000),
        'max_body_bytes' => 64 * 1024,
    ],

    /*
    |--------------------------------------------------------------------------
    | Replay
    |--------------------------------------------------------------------------
    |
    | Replays are sent from the server, so by default they may only target
    | public addresses; this prevents the replay feature from being used to
    | reach internal services (SSRF). Enable private targets only on a
    | self-hosted instance where you want to replay to localhost or your LAN.
    |
    */

    'replay' => [
        'allow_private_targets' => (bool) env('CATCHHOOK_REPLAY_ALLOW_PRIVATE', false),
        'timeout' => (int) env('CATCHHOOK_REPLAY_TIMEOUT', 10),
        'max_response_bytes' => 64 * 1024,
    ],

];
