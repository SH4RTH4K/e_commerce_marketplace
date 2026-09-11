# Dropshipping integration discovery report

Generated 8 September 2026. Discovery was completed before implementation. The completed additive-schema phase is recorded below; it has not changed an existing commerce table or imported any business data.

## Platform and operations

- Laravel is `13.18.0` (composer constraint `^13.8`) and the local CLI is PHP `8.4.25` (the project requires PHP `^8.3`).
- The application uses Inertia.js 3 with React 19 and Tailwind 4. The admin is conventional controllers plus Inertia pages, not Filament, Livewire, or Blade-only.
- The configured local defaults are MySQL, the `database` queue, the `database` cache, and the `local` filesystem. The test suite uses in-memory SQLite, array cache, and the synchronous queue.
- `composer dev` starts `queue:listen`, but the cPanel deployment guide does not establish a production supervisor. A durable worker (and its restart procedure) must be configured before supplier actions can be enabled in production.
- The scheduler is defined in `routes/console.php`; it currently contains only the `inspire` command. There is no `App\\Console\\Kernel` scheduler in use.
- There are no GitHub Actions or other CI configuration files. The existing test command is `composer test` / `php artisan test`, using PHPUnit 12.
- GD, cURL, Fileinfo, and OpenSSL are available to the local PHP CLI. Imagick and EXIF were not reported by `php -m`. No image-processing package is installed.

## Existing commerce domain

| Concern | Repository finding | Integration consequence |
| --- | --- | --- |
| Product | `products` has `category_id`, string `brand`, `regular_price`/optional `sale_price` (`decimal(12,2)`), unsigned integer `stock_quantity`, `is_published`, and no soft deletes. | There is no Brand model/table. Imports must create products with `is_published = false`; price sync must use the existing regular/sale price semantics and must not add core columns. |
| Category | `categories.parent_id` already supports hierarchy; categories have no soft deletes. | Supplier categories can map to the existing hierarchy without a core migration. Supplier hierarchy must still be mirrored independently. |
| Variants | `product_variants` stores individual `type`/`value` options with a price delta, integer stock, position, optional image path, and color code. It has no SKU or combination/parent-variant identity. | Mirror every supplier variant. Create a local variant link only when a supplier payload maps safely to this option model; do not invent a new local variant architecture. |
| Images/media | `product_images` contains `product_id`, path, alt text, primary flag, and position. `ProductImage` is also used by the media manager; current uploads go to `public/uploads/...` through `PublicUploader`. | There is no media library. Supplier image metadata/content identity must live in additive dropship tables, and only integration-owned files may be cleaned up. |
| Publishing | Storefront queries use `Product::published()` / `is_published`; the product page returns 404 for unpublished items. | The existing boolean is the draft/unpublished mechanism. |
| Price/tax/currency | Prices are decimal values; a storefront setting supplies the currency symbol. No tax-inclusive/exclusive model or product currency column was found. | The first driver may operate in BDT only after a fixture verifies its currency. Cross-currency import must be rejected until conversion policy exists. |
| Deletion | Product and category controllers call hard `delete()`; no recycle-bin or `SoftDeletes` convention exists. | A future “Delete Local Imported Product” must be explicitly designed for this application. It cannot truthfully claim recycle-bin behaviour without an approved core-domain change. |

## Authorization, flags, and conventions

- `EnsureAdmin` admits staff roles. `User` contains role and permission definitions (including `products.*` and `catalog.manage`), but the pre-existing admin routes do not use gates, policies, or permission middleware.
- No feature-flag mechanism existed before this work. The integration now supplies a narrowly scoped configuration flag and a dedicated server-side permission middleware; future dropshipping routes must use both aliases rather than rely on hidden navigation.
- There is no package or existing abstraction for supplier HTTP, media-library management, Redis/Horizon, or image optimisation. The appropriate baseline is Laravel's HTTP client, the existing public upload layer, database queues/cache, and custom additive integration models.

## Supplier evidence and fixture status

No supplier JSON fixtures were found in this repository or Downloads. The only related local artifact is `C:\\Users\\mdsha\\Downloads\\taqi-life-dropshipping.zip`, which contains historical WordPress plugin source, not a captured API response.

On 8 September 2026, an unauthenticated GET to the source-derived catalog URL returned HTTP `401`, `Content-Type: application/json`, and an empty response body. No credentials, cookies, or response data were stored. This confirms that a valid catalog fixture cannot be captured anonymously.

The source-derived (unverified) conventions are:

- base URL: `https://mohasagor.com.bd/api/reseller`;
- list endpoint shape: `/product?page=N` with an optional `category` parameter;
- authentication headers: `api-key` and `secret-key`;
- historical interpretation: `sale_price` as cost and `price` as maximum selling price.

These are not implementation facts. In particular, the historical plugin falls back to an unfiltered request for **any** non-200 category-filter response and uses heuristic payload parsing. Both behaviours are explicitly excluded from the Laravel implementation.

## Required input before phase 2

Provide sanitized, real responses from an authorised supplier account (or an approved non-production API credential) for:

1. a product-list page, including pagination;
2. a product-detail response, if available;
3. categories and a category-filter response;
4. an authentication failure response; and
5. a throttling response if observable/documented.

They will be stored under `tests/Fixtures/dropshipping/mohasagor/` only after tokens, cookies, customer data, and authentication headers are removed. The payloads must establish product/variant/category identities, price meanings, availability and stock semantics, image fields, pagination, supported filtering, and actual capabilities. No supplier parser, client, or catalog job should be written before that evidence exists.

## Next approved implementation phase

After fixtures are available, implement and test the supplier-neutral driver contract, Mohasagor normalizer/client, and pricing engine as one reviewable phase. It will use additive `dropship_*` tables only, queue all supplier network work, and leave order submission unimplemented unless its API is independently verified.

## Implementation progress: additive schema and models

Completed on 8 September 2026:

- Added additive `dropship_*` tables for suppliers, mirrored categories/products/variants, category mappings, local product/variant links, durable sync runs, and per-run items.
- Added matching `Dropship*` Eloquent models under `app/Models`. Supplier credentials use Laravel encrypted casts and are hidden from serialization.
- Preserved the current `products`, `categories`, `product_variants`, and `product_images` schemas. Supplier and local records are connected by nullable foreign keys, so removing a local product or variant does not delete the mirror record.
- Added a schema-level PHPUnit test.

Validation: PHP syntax checks passed for all added files, and `php artisan migrate --pretend` generated valid MySQL DDL. PHPUnit cannot run in this environment because the repository config selects SQLite in memory but the local PHP has `pdo_mysql` only and no `pdo_sqlite` driver. No local database migration was executed.

## Implementation progress: pricing engine

Completed on 8 September 2026:

- Added the supplier-neutral `PricingEngine` and immutable `PriceBreakdown` value object under `app/Services/Dropshipping`.
- The engine accepts normalized cost and optional supplier/merchant ceilings; it does not assign meaning to any supplier payload field.
- It supports fixed/percentage minimum and selling markup, configurable increment rounding, explicit conflict states, and warnings whenever a ceiling limits the preferred selling markup.
- `tests/Unit/PricingEngineTest.php` passes: 5 tests and 19 assertions. These tests do not require the unavailable SQLite extension.

## Implementation progress: feature flag and authorization

Completed on 8 September 2026:

- Added `DROPSHIPPING_ENABLED=false` to `.env.example` and `config/dropshipping.php`; supplier functionality remains disabled until explicitly enabled in a staged deployment.
- Registered `dropshipping.manage` in the existing staff-permission catalogue.
- Added `permission` and `dropshipping.enabled` middleware aliases. Future integration routes will require both aliases in addition to the existing `admin` middleware.
- `tests/Unit/DropshippingAccessTest.php` passes. Together with pricing tests: 7 tests and 24 assertions.

## Implementation progress: supplier contracts and registry

Completed on 8 September 2026:

- Added a supplier-neutral `SupplierClient` contract, immutable capabilities/connection/product/category/variant/page DTOs, and an application-owned `SupplierRegistry`.
- Driver resolution accepts only stable registered keys, never a database-supplied PHP class name. No Mohasagor driver is registered yet.
- Product-page DTO validation rejects non-advancing pagination, preventing an unknown supplier pagination shape from creating an infinite loop.
- `tests/Unit/Dropshipping/SupplierRegistryTest.php` passes. Pricing, access, and contract tests: 10 tests and 30 assertions.

## Implementation progress: sync-run state and idempotency

Completed on 8 September 2026:

- Added `dropship_sync_run_items`, with a unique `(sync_run_id, item_key)` constraint, to give queued work durable per-item idempotency and accurate run counters.
- Added cancellation-aware run/item states and `SyncRunService` methods to queue unique items, start one item only once, record its final state, and finalize a fully processed run.
- The service serializes counter updates with a row lock and uses `insertOrIgnore` for concurrent duplicate dispatches.
- Sync-state tests pass; the combined pure unit suite now has 12 tests and 40 assertions. The new migration also appears as valid MySQL DDL under `php artisan migrate --pretend`.

## Implementation progress: supplier image URL validation

Completed on 8 September 2026:

- Added a strict image URL validator that allows HTTPS only, rejects user-info and non-default ports, requires an exact driver-owned host allow-list, and rejects DNS answers that are private, reserved, malformed, or unresolved.
- Each redirect target must be passed through the validator independently. A future download transport must pin the validated public IPs to prevent DNS rebinding.
- This phase does not download, transform, or attach supplier media; it only provides the first SSRF boundary for the future image job.
- Image URL, sync state, contract, access, and pricing tests pass: 15 tests and 47 assertions.

## Implementation progress: category mapping

Completed on 8 September 2026:

- Added `CategoryMapper` for supplier-scoped, explicit manual mappings to the existing hierarchical `categories` table.
- Mapping, replacing a mapping, and unmapping do not create or change core categories. The same supplier category key can resolve differently for a different supplier.
- Added a database-backed feature test for this behaviour; it will run in CI/a PHP environment with SQLite PDO. Local syntax validation passed, while local execution remains blocked by the missing `pdo_sqlite` extension.
- The executable pure unit suite now has 16 tests and 50 assertions.

## Implementation progress: draft product import

Completed on 8 September 2026:

- Added `ProductImportService`, which imports only from a locked local mirror row, requires a manual category mapping, calculates a constrained selling price, and always creates the local product with `is_published = false`.
- A unique source-product link plus a mirror-row lock makes a repeated import return the existing local product instead of creating a duplicate.
- Initial field ownership enables only price and stock for future supplier sync; title, SKU, category, and images remain merchant-owned. Local variants and images are deliberately not created in this phase.
- The service rejects non-BDT data rather than silently applying a missing currency-conversion policy.
- Added database-backed coverage for mapped draft import, idempotency, and blocked imports. Local syntax validation passed; executing these feature tests still requires `pdo_sqlite`.

## Implementation progress: price and stock sync

Completed on 8 September 2026:

- Added `PriceStockSyncService`, which locks the source, link, and local product before updating only price and/or stock fields explicitly enabled in `field_sync_rules`.
- It preserves title, SKU, category, images, variants, and `is_published`; supplier stock that is unknown is not converted to zero.
- Invalid prices or unsupported currency produce a sanitized warning without overwriting a local price. A price failure can still allow a separately valid stock update.
- Added database-backed coverage for merchant-edit preservation and unknown-stock handling. Syntax validation passes locally; execution needs `pdo_sqlite`.

## Implementation progress: local supplier catalog mirror

Completed on 8 September 2026:

- Added `SupplierCatalogMirrorService`, which upserts already-normalized categories, products, and variants into the additive supplier mirror tables only.
- Repeated pages or products reuse the supplier-scoped identity and update the existing mirror row. Payload hashes use a canonical JSON representation to make unchanged payload detection stable.
- Raw optional payload encoding degrades to an empty object rather than failing an entire run on malformed optional metadata.
- Added a database-backed idempotency test for category/product/variant mirroring. It passes syntax validation locally; execution needs `pdo_sqlite`.

## Implementation progress: queued catalog refresh

Completed on 8 September 2026:

- Added `StartSupplierCatalogSync` and `SyncSupplierCatalogPage` queue jobs. All supplier client calls occur only in these workers, never in an admin request.
- The start job mirrors driver-supported categories and dispatches page work. Page jobs mirror normalized products, track page-level (not product-import) progress, and schedule another page only when the DTO supplies safe pagination information.
- Cancellation prevents new work from starting. A transient page exception returns the run item to queued for Laravel retry; only an exhausted retry becomes a failed item and a completed-with-errors run.
- No supplier driver is registered yet, so these jobs cannot make a live request until fixture-backed driver implementation is complete. Database-backed job tests were added and need `pdo_sqlite` to execute locally.

## Implementation progress: queued price and stock sync

Completed on 8 September 2026:

- Added start/item queue jobs for price-and-stock sync from the local supplier mirror. They queue only active linked products and reuse the ownership-safe `PriceStockSyncService`.
- A pricing conflict or unsupported currency becomes a failed run item with a sanitized summary; unknown stock remains a non-destructive warning.
- Item retries requeue safely and exhausted retries are counted once. The feature test verifies that a price-and-stock operation is dispatched as a job; it needs `pdo_sqlite` for local execution.

## Implementation progress: queued supplier connection checks

Completed on 8 September 2026:

- Added a queue-only supplier connection-check job and additive connection-status fields on `dropship_suppliers` for last test, last success, capabilities, and a fixed sanitized result message.
- The job intentionally persists no driver-supplied response detail or exception message, preventing credentials or raw API content from appearing in status screens.
- Added a database-backed dispatch test. PHP syntax checks and the pure unit suite pass. The current local MySQL service is refusing connections, so this newest migration could not be dry-run; no migration was applied.

## Implementation progress: CLI dispatch

Completed on 8 September 2026:

- Added the registered `dropship:sync` command with `--catalog`, `--price-stock`, and `--product=SUPPLIER_ID` modes.
- The command checks the feature flag and active supplier, creates a durable run, and dispatches queue jobs. It explicitly rejects unsupported `--images` requests instead of pretending the image pipeline exists.
- The command is visible in `php artisan list`; database-backed command tests are included but require a working test database driver.

## Implementation progress: scheduling and retention

Completed on 8 September 2026:

- Added feature-flagged schedules in `routes/console.php`: catalog at `0 2 * * *`, price/stock hourly, and `dropship:prune` at 03:30.
- Scheduled callbacks skip suppliers with an active run, dispatch queue jobs only, and use `withoutOverlapping` plus `onOneServer` locks.
- Added `dropship:prune`; it removes only terminal sync-run records older than the configured retention period (minimum seven days). It cannot delete products, categories, or media.
- With `DROPSHIPPING_ENABLED=false`, `schedule:list` correctly reports no scheduled tasks. With the flag enabled and an array cache override, all three schedules were confirmed. The normal database cache path currently cannot acquire its lock because local MySQL is unavailable.

## Implementation progress: admin dashboard

Completed on 8 September 2026:

- Added the Inertia/React supplier dashboard at `/admin/dropshipping`, showing local supplier mirror counts, sanitized connection state, and recent sync-run progress.
- Added queued Test, Catalog, Price/Stock, and Cancel actions. Every route requires `admin`, `testing.readonly`, `dropshipping.enabled`, and `permission:dropshipping.manage`; page requests never call a supplier.
- Added a feature-gated Dropshipping navigation item and shared `dropshipping_enabled` prop. Credentials are not sent to the browser.
- PHP syntax and route registration passed. The repository frontend build was attempted but cannot run because `npm` is not installed in the current environment.

## Implementation progress: supplier configuration

Completed on 8 September 2026:

- Added permission- and feature-gated admin actions to create, update, and enable/disable supplier records, with inline editing in the existing Inertia dashboard.
- Supplier driver selection is restricted to application-registered stable driver keys; arbitrary PHP classes cannot be entered through the UI.
- Base URLs must be HTTPS. API and secret credentials are write-only in the form and remain protected by the model's encrypted casts; they are never returned as Inertia props.
- The dashboard displays a configuration notice while no supplier driver is registered. This is intentional: no live supplier can be enabled until an evidence-backed driver is implemented.

Validation: controller syntax and all eight dropshipping routes pass. The focused pure unit suite passed (16 tests, 50 assertions) before the local test/runtime prerequisites were completed; the full-suite and frontend results are recorded below.

## Validation and local readiness update

Completed on 8 September 2026:

- Enabled the already-installed `pdo_sqlite` and `sqlite3` extensions in the local PHP CLI configuration. The full PHPUnit suite now passes: 67 tests and 232 assertions.
- Applied the three additive dropshipping migrations to the local MySQL database. MySQL required explicit short names for several composite unique indexes; the migration now uses names below the server's identifier-length limit.
- Installed a self-contained Node.js 24.19 runtime for local validation and completed `npm run build`. Vite builds successfully; it reports only the existing large-chunk advisory.
- Added production deployment guidance for a durable database queue worker, `queue:restart`, and the once-per-minute scheduler cron. Production defaults keep `DROPSHIPPING_ENABLED=false`.

The remaining external prerequisite is unchanged: no sanitized authenticated supplier fixtures or registered live driver are available. The implementation must not invent those payloads or credentials.

## Implementation progress: legacy interface parity and connection setup

Completed on 8 September 2026:

- Reviewed the original public `taqi-life-dropshipping` WordPress plugin interface and carried its four-step product-posting workflow and API settings concepts into the Laravel/Inertia dashboard.
- The previously reviewed `mohasagor` connection probe remains available in the codebase for testing, but it is no longer registered by default. No supplier driver appears in the admin dropdown until an administrator creates and enables a database-backed REST profile.
- The admin page now exposes Supplier Name, business key, HTTPS API Base URL, optional category endpoint, masked API/secret fields, imported-status preference, image/variation preferences, workflow progress, and the existing queued actions.
- Connection failures remain sanitized, and credentials stay encrypted/write-only.

The source interface reviewed is the repository's [`taqi-life-dropshipping.php`](https://github.com/SH4RTH4K/taqi_life/blob/main/wp-content/plugins/taqi-life-dropshipping/taqi-life-dropshipping.php). Catalog normalization still requires real sanitized fixtures before product sync is enabled.

Validation after this phase: 69 PHPUnit tests and 237 assertions pass; the Vite production build succeeds with only the existing large-chunk advisory.

## Implementation progress: page-wise admin navigation

Completed on 8 September 2026:

- Replaced the single Dropshipping navigation item with a journey-ordered submenu: Dropshipping dashboard, API Settings, Pricing Rules, Category Mapping, Variation Mapping, Supplier Products, Imported Products, Media Cleanup, Storage Usage, and Database Migration.
- Added separate protected routes, controller methods, and Inertia pages for each submenu destination. Supplier products, imported products, storage, schema status, category/variation mirror state, and pricing settings read only from local integration data.
- Kept destructive media cleanup and schema migration actions read-only until their ownership/approval workflows are fully specified.

## Operator journey audit

Completed on 8 September 2026:

- Dashboard now links its workflow cards to the real next pages: API Settings, Pricing Rules, Supplier Products, and Imported Products.
- Pricing Rules now persists validated supplier-scoped markup, rounding, and ceiling rules.
- Category Mapping now saves explicit supplier-to-local mappings.
- Variation Mapping now permits a supplier variant to be linked only to a local variant belonging to the already-linked product.
- Supplier Products now imports one mirrored item as a draft through `ProductImportService`.
- Imported Products now permits publishing only products proven to have been created by the integration.
- Media cleanup, storage usage, and database migration remain read-only because automatic deletion and schema mutation require separate approval/ownership workflows.

Validation after the audit: PHP syntax checks pass, the Vite build succeeds, and the full PHPUnit suite passes (69 tests, 237 assertions).

## Implementation progress: admin-managed REST driver profiles

Completed on 8 September 2026:

- Added `dropship_driver_profiles`, so an administrator can create, enable, and disable additional supplier drivers from API Settings without a code deployment.
- Profiles contain only endpoint paths, header names, default currency, and explicit JSON field mappings. The registry always uses the fixed `ConfigurableRestClient`; it never evaluates a PHP class name from the database.
- The generic client enforces HTTPS, public DNS resolution, relative endpoint paths, bounded timeouts, sanitized connection results, and encrypted supplier credentials. Mapped product pages and categories can be mirrored when the administrator supplies the supplier's real response mapping.
- The API Settings page now has an “Add configurable REST driver” form with a JSON mapping example and a profile enable/disable list. New profiles become selectable supplier drivers on the next request.

Validation after this phase: the profile migration applied locally, all 24 dropshipping routes register, 69 PHPUnit tests and 237 assertions pass, and the Vite production build succeeds with only the existing large-chunk advisory.

## Implementation progress: supplier price mapping and structured pricing

Completed on 8 September 2026:

- Added per-supplier `price_field_mapping` storage. Supplier adapters apply that mapping at the normalization boundary; the pricing engine receives normalized cost and maximum values and does not read supplier payload field names.
- Replaced the legacy single-markup UI with independent Selling, Minimum, and Maximum formula cards. Each supports cost/maximum base selection, no/plus/minus fixed and percentage adjustments, independent caps, discount protection, and rounding.
- Added explicit conflict and missing-data handling. Minimum and maximum values are always calculated; caps only control enforcement. A disabled maximum cap no longer changes a selling formula.
- Added a backend price-preview endpoint that uses the production `PricingEngine`, plus structured `PriceBreakdown` values and an integer-minor-unit `PriceAdjustmentCalculator`.
- Added supplier mapping controls and preview values to the Pricing Rules page. Mapping and seller pricing rules are saved separately.

Validation after this phase: the additive mapping and pricing-snapshot migrations applied locally, 76 PHPUnit tests and 267 assertions pass, and the Vite production build succeeds with only the existing large-chunk advisory.

The pricing UI now exposes separate rounding controls for Selling Price, Minimum Price, Maximum Price, and Discounted Price. Formula-specific rounding is applied before cap enforcement. Discounted Price is intentionally based on the computed Regular Selling Price after enabled minimum and maximum caps, not directly on Supplier Cost Price or Supplier Maximum Price. Discount rounding runs after the customer discount, with minimum protection applied afterward when enabled. Validation after this refinement: 78 PHPUnit tests and 274 assertions pass.
