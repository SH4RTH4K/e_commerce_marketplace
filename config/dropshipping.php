<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Dropshipping integration feature flag
    |--------------------------------------------------------------------------
    |
    | Keep supplier functionality disabled until queue workers, credentials,
    | and a staged draft-only rollout have been verified in the target
    | environment.
    |
    */
    'enabled' => (bool) env('DROPSHIPPING_ENABLED', false),

    'schedule' => [
        'catalog_cron' => env('DROPSHIPPING_CATALOG_CRON', '0 2 * * *'),
        'price_stock_cron' => env('DROPSHIPPING_PRICE_STOCK_CRON', '0 * * * *'),
        'retention_days' => (int) env('DROPSHIPPING_RUN_RETENTION_DAYS', 90),
    ],

    // The browser fallback processes this many imported products at once when
    // no background worker is available. Keep this bounded for slow supplier APIs.
    'imported_sync' => [
        'browser_batch_size' => min(25, max(1, (int) env('DROPSHIPPING_IMPORTED_SYNC_BROWSER_BATCH_SIZE', 10))),
    ],
];
