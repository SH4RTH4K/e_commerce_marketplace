# Local supplier data and dropshipping setup

Completed against the existing local MySQL database `taqi_life_laravel` on `127.0.0.1`. The local application is available at `http://127.0.0.1:8000`. No local backup was created, following the user's instruction. No live deployment or push was performed.

## Existing data carried forward

| Record | Result |
| --- | --- |
| Business supplier | Mohasagor Bangladesh, existing ID 1, code SUP-00001 |
| Provider / stable integration key | mohasagor / main-supplier |
| Supplier catalog | 3,101 records |
| Store products / existing import links | 1,363 / 1,363 |
| Published / unpublished | 1,356 / 7 |
| Sync history | 35 runs retained |
| Automatic supplier/source association | All 1,363 imported products associated; zero conflicts |
| Supplier records | One existing supplier retained; no duplicate created |

Repeated `php artisan suppliers:backfill-products` returned `Updated: 0; unchanged: 1363; conflicts: 0` after migration.

Before/after SHA-256 fingerprints matched for:

- Product IDs, names, slugs, SKUs, regular/sale prices and stock: `19e51cf807c4bd8052f4b3fd6e7769382a90be1719279f847a7e019cb8c457e6`.
- Complete existing product-link rows: `1bbab6fa6ba29f1bf6663a77fd3f550a4ef19affc1536f6e4e990b8dbe3ee843`.
- Supplier ID/key/name, provider, API URL, encrypted credentials, pricing, sync and price-field settings: `2b9b2bd551ad4dc70845bbff6d0e763455412c876d5e74973e66c9987e6a6318`.

Only the intended supplier/source association and additive schema fields were applied. The previous isolated review server on port 8318 was stopped to avoid confusing its sample database with the actual local store.

## Simplified workflow

1. Connect supplier: open the existing saved connection by default. Mohasagor uses its built-in provider. Custom API profiles, mappings and technical options are collapsed under Advanced settings.
2. Prepare prices and categories: reuse saved pricing; map the categories being imported. Mapping every supplier category is not a prerequisite.
3. Choose products: browse the saved supplier catalog and import selections. Catalog records and imported store products have separate counts.
4. Review and publish: the dashboard's Continue action opens the seven actual unpublished Mohasagor products. Product variations can be mapped after import when needed.

Supplier selection persists between workflow pages, list filters and pagination. Explicit All suppliers is available. Catalog, imported-product, category and pricing queries respect the selected supplier. Sync controls and history stay in Dropshipping; contacts, business details, manual suppliers and reports remain in Supplier Management.

Blank credentials retain their saved encrypted values. Unchecked import-image/variation flags now submit an explicit false value. Ordinary settings edits preserve an omitted category API path. Supplier activation/deactivation through Dropshipping records actor/audit history, and ordinary manual suppliers must be managed through Supplier Management.

## Changed files

- `app/Http/Controllers/Admin/DropshippingController.php`: selected supplier resolution, separate counts, scoped queries, protected settings preservation, audited activation.
- `app/Http/Controllers/Admin/SupplierController.php`: permission-aware navigation to the linked integration.
- `resources/js/Layouts/AdminLayout.jsx`: menu order follows the setup journey.
- `resources/js/Pages/Admin/Dropshipping/Index.jsx`: provider summary, real counts, four steps and next action.
- `resources/js/Pages/Admin/Dropshipping/ApiSettings.jsx`: saved connection first, collapsed advanced configuration, explicit import flags and processing feedback.
- `resources/js/Pages/Admin/Dropshipping/Subpage.jsx`: supplier context and workflow navigation.
- `resources/js/Pages/Admin/Dropshipping/SupplierProducts.jsx` and `ImportedProducts.jsx`: supplier filters retained through pagination and clearing other filters.
- `resources/js/Pages/Admin/Suppliers/Show.jsx`: permission-aware link to the supplier's dropshipping workspace.
- `tests/Feature/DropshippingSetupExperienceTest.php`: five backend regressions.
- `tests/js/DropshippingSetup.test.mjs`: five component/workflow regressions.
- `public/build/`: regenerated deployable assets.

## Local migrations applied

No new migration was introduced by this UX follow-up. Nine existing pending additive migrations were applied as local batch 36:

- `2026_10_15_000001` through `2026_10_15_000004`: order-item discounts, product pricing fields, existing inventory ledger, customer notes.
- `2026_10_18_000001` through `2026_10_18_000005`: product supplier relationship, supplier type, supplier management/source fields and backfill, supplier audits, inventory movement context.

All local migrations now report Ran. No product table was recreated and no existing store record was deleted.

## Verification and limits

- Full PHP suite: **377 tests, 376 passed, 3,541 assertions, one previously reported failure** in `StorefrontSeoTest::test_paginated_catalogue_is_indexable_self_canonical_and_has_crawlable_links` at line 34. This unrelated storefront pagination issue predates this supplier work; storefront code was not changed here.
- Component suite: **54 passed, zero failures**.
- Production asset build: passed, with the existing template-1 Poppins font-resolution warnings.
- PHP syntax and whitespace checks: passed.
- Browser verified against actual local MySQL: independent supplier list shows Mohasagor and 1,363 associated products; saved connection is selected; catalog has 3,101 records; Continue shows exactly seven unpublished products.
- Screenshot: `supplier-review/mohasagor-dashboard-local.png`.

The connected external provider was not newly synchronized during this UX validation. Historical failed syncs and already queued jobs were retained. No mass import, publication, external API configuration change or background queue processing was triggered. Product import/sync/ownership behavior is covered by the regression suite. Live provider validation, production preflight/backup and live deployment still require the separate release process and the user's deployment confirmation.

## Local entry points

- Overview: `http://127.0.0.1:8000/admin/dropshipping`
- Business supplier: `http://127.0.0.1:8000/admin/suppliers/1`
- Connection: `http://127.0.0.1:8000/admin/dropshipping/settings?supplier_id=1`
- Catalog: `http://127.0.0.1:8000/admin/dropshipping/products?supplier_id=1`
- Imported products: `http://127.0.0.1:8000/admin/dropshipping/imported?supplier_id=1`
