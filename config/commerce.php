<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Stock concurrency strategy
    |--------------------------------------------------------------------------
    |
    | "optimistic"  — versioned UPDATE, no locking, retries on conflict.
    |                 High throughput; best for most real-world workloads.
    | "pessimistic" — SELECT ... FOR UPDATE, serializes writers per row.
    |                 Use when conflicts are frequent and retries cost more than queuing.
    */
    'stock_strategy' => env('STOCK_STRATEGY', 'optimistic'),

    /*
    |--------------------------------------------------------------------------
    | Payment driver
    |--------------------------------------------------------------------------
    | "stripe" — real Stripe API; requires STRIPE_SECRET.
    | "mock"   — deterministic mock used for tests & load simulations.
    */
    'payment_driver' => env('PAYMENT_DRIVER', 'mock'),

    /*
    |--------------------------------------------------------------------------
    | Load Balancer Configuration (Requirement 5)
    |--------------------------------------------------------------------------
    */
    'load_balancer' => [
        'strategy' => env('LB_STRATEGY', 'round_robin'), // round_robin | weighted_round_robin | least_connections
        'servers'  => null, // null = use default (3 servers). Override with array of server configs.
    ],
];
