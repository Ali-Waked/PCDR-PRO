<?php

declare(strict_types=1);

return [
    /*
     * Explicit opt-in for temporary preview seeding. Leave unset/false for
     * real production and Hostinger deployments.
     */
    'initialize' => env('PCDR_PREVIEW_INIT', false),

    /*
     * Explicit second opt-in for the internally tracked demo dataset. Both
     * PCDR_PREVIEW_INIT and PCDR_DEMO_SEED must be true before the dedicated
     * DemoDatabaseSeeder or cleanup command may run. Production is always
     * refused by the seeder guard, regardless of these flags.
     */
    'demo_seed' => env('PCDR_DEMO_SEED', false),

    'marker' => [
        'group' => 'preview',
        'key' => 'initialized_at',
    ],
];
