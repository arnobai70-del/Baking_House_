<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Deployment security (Phase 9)
    |--------------------------------------------------------------------------
    |
    | trusted_proxies: comma-separated proxy IPs / CIDRs (or "*" only behind a load balancer
    |   that overwrites X-Forwarded-*). Needed so HTTPS, client IPs and rate limits are right.
    | force_https: generate https:// URLs (staging and production).
    | staging_gate: HTTP Basic protection for the staging site so it is never public. Password
    |   is stored as a bcrypt hash (php artisan tinker → bcrypt('…')). Webhooks and /up stay open.
    |
    */

    'trusted_proxies' => env('TRUSTED_PROXIES'),

    'force_https' => (bool) env('FORCE_HTTPS', false),

    'staging_gate' => [
        'enabled' => (bool) env('STAGING_GATE_ENABLED', false),
        'user' => env('STAGING_GATE_USER'),
        'password_hash' => env('STAGING_GATE_PASSWORD_HASH'),
        'open_paths' => ['up', 'api/v1/webhooks/*'],
    ],

];
