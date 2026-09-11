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
];
