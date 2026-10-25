<?php

return [
    'page_cache' => [
        'enabled' => (bool) env('STOREFRONT_PAGE_CACHE_ENABLED', true),
        'ttl' => max(1, (int) env('STOREFRONT_PAGE_CACHE_TTL', 300)),
        'store' => env('STOREFRONT_PAGE_CACHE_STORE', null),
    ],
];
