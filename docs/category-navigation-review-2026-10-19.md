# Category navigation improvement — 19 October 2026

Status: implemented, built, and verified locally; awaiting confirmation before live deployment.

## Result

Category navigation now replaces the old content with a clean product skeleton inside the main content area. The destination name, a small loading indicator, filter placeholders, and product cards make the action visible without a floating message over the site header. Desktop dropdowns and the mobile menu close on navigation. Skeleton columns follow the administrator's product-per-row setting and existing responsive catalogue breakpoints. Reduced-motion preferences disable animation.

Category links prefetch after hover intent with a ten-second lifetime. No categories are fetched on mount. The transition handles cached responses, quick changes between categories, cancellation, HTTP errors, and network failures. Filtering and sorting retain the current page with inline feedback; background Load More requests keep their existing local indicator.

## Performance

The catalogue previously selected full product descriptions and specifications, loaded full supplier mirror rows just to find a SKU, and queried variant existence separately for each card despite already selecting that flag. The response now fetches only the fields needed for cards, narrows supplier relations, and reuses the variant flag. Product detail pages retain their complete content. Sidebar/filter data is lazy and skipped on product-only pagination; the overall product count and price ceiling share one aggregate query.

Read-only measurements against the existing local MySQL catalogue, using Laravel's HTTP kernel and Inertia JSON responses:

| Request | SQL queries before → after | JSON bytes before → after |
| --- | --- | --- |
| Shop | 32 → 16 | 118,532 → 41,763 |
| Category (Men's Fashion) | 36 → 20 | 98,898 → 39,864 |
| Product-only Load More | 28 → 7 | 64,232 → 21,436 |

Category JSON is approximately 60% smaller. Full-page figures compare fresh PHP processes; partial pagination figures compare warmed requests. These measure local application work and uncompressed payloads, not guaranteed production latency. No persistent product/stock cache was introduced. Prices, stock, and sidebar filters are queried fresh; hover-prefetched navigation may reuse its response for up to ten seconds. Existing ordering validation remains authoritative.

The live Shirt category was inspected read-only. Direct CLI requests returned an edge HTML response, so their timing was excluded from the application benchmark. The local catalogue has no Shirt category; existing Men's Fashion data was used for visual checks and isolated automated fixtures cover `/category/shirt`.

## Validation

- `npm.cmd run test:components`: 67 tests passed.
- `php artisan test --compact --filter="StorefrontCatalogPerformanceTest|Shop|StorefrontCategoryTreeTest|CategoryHierarchyWorkflowTest|ProductUrlTest"`: 26 tests passed, 448 assertions.
- Additional backend regression checks by the performance review passed (28 tests, 458 assertions).
- Production asset build and `git diff --check` passed. Existing missing Template 1 Poppins font warnings remain unchanged.
- Existing SEO regression suite still has its previously known pagination fallback link failure at `StorefrontSeoTest.php:34`; five other checks passed. This update does not modify that fallback template.
- Browser review at desktop, 834 × 1112 tablet, and 390 × 844 mobile sizes: skeleton renders without horizontal overflow, menus dismiss, and actual products replace the skeleton. A temporary local proxy delayed category responses by four seconds only to inspect the loading state. It is not part of the release; the normal local route also loaded successfully after removing the delay.

Screenshots:

- [Desktop](category-review/category-loading-desktop.jpg)
- [Tablet](category-review/category-loading-tablet.jpg)
- [Mobile](category-review/category-loading-mobile.jpg)

No migration, product data update, supplier configuration change, remote push, or live deployment was performed for this update.
