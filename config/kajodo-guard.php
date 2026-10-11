<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Kajodo API connection
    |--------------------------------------------------------------------------
    |
    | base_url is your Kajodo service (e.g. https://intel.example.com, no
    | trailing slash). api_key must carry the `lookup` ability and be bound
    | to the website in origin — Kajodo rejects keys without a matching
    | Origin/Referer header (403).
    |
    */

    'base_url' => env('KAJODO_BASE_URL', 'http://localhost:8000'),
    'api_key' => env('KAJODO_API_KEY', ''),
    'origin' => env('KAJODO_ORIGIN', env('APP_URL', 'http://localhost')),
    'timeout' => (int) env('KAJODO_TIMEOUT', 3),

    /*
    |--------------------------------------------------------------------------
    | Result cache (required — Kajodo throttles lookups at 120/min)
    |--------------------------------------------------------------------------
    */

    'cache_enabled' => env('KAJODO_CACHE_ENABLED', true),
    'cache_ttl' => (int) env('KAJODO_CACHE_TTL', 3600),
    'cache_store' => env('KAJODO_CACHE_STORE'),

    /*
    |--------------------------------------------------------------------------
    | Visitor store (long-term memory — a stored IP is never asked
    | to Kajodo again, so one API call serves every repeat visit)
    |--------------------------------------------------------------------------
    |
    | retention_days controls pruning only: kajodo:prune-visitors deletes
    | rows not seen for this long. A visit touches the row, so active
    | visitors are kept.
    |
    */

    'store_enabled' => env('KAJODO_STORE_ENABLED', true),
    'table' => env('KAJODO_TABLE', 'kajodo_visitors'),
    'retention_days' => (int) env('KAJODO_RETENTION_DAYS', 90),

    /*
    |--------------------------------------------------------------------------
    | Failure behaviour — fail-open was chosen for this package.
    |--------------------------------------------------------------------------
    |
    | When the Kajodo API is unreachable, times out, or answers with a
    | non-2xx status, visitors are ALLOWED through and the failure is
    | logged. Set to false to deny instead (fail-closed).
    |
    */

    'fail_open' => env('KAJODO_FAIL_OPEN', true),

    'deny_message' => env('KAJODO_DENY_MESSAGE', 'Not available in your region.'),
    'deny_status' => (int) env('KAJODO_DENY_STATUS', 403),

    /*
    |--------------------------------------------------------------------------
    | Signed-in users
    |--------------------------------------------------------------------------
    |
    | true (default) = only guests are gated. They already authenticated,
    | and gating everyone locks an admin out the moment they browse from a
    | VPN or datacenter network.
    |
    | false = gate every request, signed in or not.
    |
    */

    'skip_authenticated' => env('KAJODO_SKIP_AUTHENTICATED', true),

    /*
    |--------------------------------------------------------------------------
    | Paths that bypass the check (webhooks, health checks, ...).
    |--------------------------------------------------------------------------
    */

    'except' => [],
];
