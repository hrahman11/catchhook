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
