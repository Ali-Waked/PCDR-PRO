<?php

declare(strict_types=1);

return [
    /*
     * Explicit opt-in for temporary preview seeding. Leave unset/false for
     * real production and Hostinger deployments.
     */
    'initialize' => env('PCDR_PREVIEW_INIT', false),

    'marker' => [
        'group' => 'preview',
        'key' => 'initialized_at',
    ],
];
