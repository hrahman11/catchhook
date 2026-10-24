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

];
