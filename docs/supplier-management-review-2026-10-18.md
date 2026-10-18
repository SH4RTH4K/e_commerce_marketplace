# Supplier management implementation and second review

Deployment status: **local only; user confirmation required before pushing or deploying**.

The first implementation report overstated completeness. This second review found missing independent permissions, incomplete supplier retrieval, inadequate imported-product ownership protection, missing audit/report flows, incomplete inventory attribution, and no supplier-specific acceptance tests. These have been implemented and exercised locally.

## Business workflow

1. **Suppliers → Add Supplier** creates a business record with contact/address/business details, unique code, status, and creator/updater history. No API credentials are accepted by core CRUD.
2. **Products → Add New Product → Source & Supplier** selects an active supplier or Own Inventory. An inline Add Supplier dialog preserves the unsaved product form, images, and variants. Selecting a dropshipping business supplier keeps a manually entered product manual.
3. **Integrations → Dropshipping → API Settings** explicitly links an existing active supplier without an API connection, or creates one new business supplier. Existing stable supplier IDs and provider keys are reused.
4. Imports assign the linked supplier and preserve the actual external product ID. Ownership checks run before content, images, prices, or stock are touched. The ordinary editor locks imported ownership; a validated Re-link & Sync action requires confirmation and a reason and records an audit.
5. **Suppliers → Supplier details / Supplier Reports** shows actual product/source counts and stored inventory references. Filters and CSV exports use recorded data.
6. **Inventory / Stock → Stock History** retains receipt supplier snapshots, movement type, quantity before/after, reference, staff/guest actor, timestamp, and stock scope. Order reservation and cancellation movements share the existing ledger.

## Architecture and compatibility

- Reuses `dropship_suppliers` as the central supplier registry because existing product/import/stock foreign keys already reference its stable IDs. Its historical table/model name does not restrict the core supplier UI to API suppliers. No parallel supplier registry is introduced.
- `SupplierController` and `Admin/Suppliers/*` serve `/admin/suppliers`. The separate Dropshipping dashboard serves `/admin/dropshipping`.
- Business supplier type and integration configuration are independent. A local or wholesale supplier can acquire an integration without changing its business identity.
- Dedicated `suppliers.view` and `suppliers.manage` permissions work when Dropshipping is disabled. Manage includes view. Product assignment requires existing product-edit authorization.
- Existing API client implementations, provider endpoints, API authentication headers, category/variation mappings and external identifiers are retained.
- Local physical units and supplier-managed availability are separated in inventory and analytics totals. API imports do not create a local warehouse receipt.
- Purchase/cost price is internal and excluded from public product serialization.
- Supplier records have no destructive deletion endpoint. Deactivation preserves products, inventory references, and audits.

## Files

| Area | Main changed/new files |
| --- | --- |
| Core suppliers | `app/Http/Controllers/Admin/SupplierController.php`, `app/Models/DropshipSupplier.php`, `app/Models/SupplierAudit.php`, `app/Models/User.php`, `resources/js/Pages/Admin/Suppliers/{Index,Form,Show,Report,Shared}.jsx` |
| Product source/assignment | `app/Http/Controllers/Admin/ProductController.php`, `app/Models/Product.php`, `app/Services/ProductCatalogService.php`, `resources/js/Pages/Admin/Products/{Form,Index}.jsx`, `resources/js/Components/Admin/QuickSupplierDialog.jsx` |
| Imports and integration | `app/Services/Dropshipping/{SupplierAssociationService,SupplierOwnershipException,ProductImportService,PriceStockSyncService,PriceStockSyncResult}.php`, `app/Console/Commands/BackfillProductSuppliers.php`, `app/Http/Controllers/Admin/DropshippingController.php`, sync item jobs, `resources/js/Pages/Admin/Dropshipping/{ApiSettings,ImportedProducts,Index}.jsx` |
| Inventory and reports | `app/Services/InventoryStockService.php`, inventory/analytics controllers, `app/Models/InventoryAdjustment.php`, `resources/js/Components/Admin/InventoryDialogs.jsx`, `resources/js/Pages/Admin/Inventory/{Index,History}.jsx`, `resources/js/Pages/Admin/Analytics.jsx` |
| Order stock references | checkout, quick order, landing page, admin order and recovered checkout controllers; `app/Models/Order.php` |
| Routing/navigation | `routes/web.php`, `resources/js/Layouts/AdminLayout.jsx` |
| Build | `public/build/manifest.json`, generated JS/CSS assets |

## Pending additive migrations

These migrations have **not** run on production:

- `2026_10_18_000003_add_supplier_management_fields.php`: supplier contact/business fields and unique codes; nullable internal purchase price and indexed product source; supplier foreign key on the existing inventory ledger; normalize legacy manual supplier type; backfill only validated integration-owned products.
- `2026_10_18_000004_add_supplier_audit_history.php`: nullable creator/updater references and supplier audit records with actor snapshots.
- `2026_10_18_000005_add_movement_context_to_inventory_adjustments_table.php`: movement type/reference/actor snapshot/stock scope on the existing inventory ledger.

The ownership backfill creates no suppliers, retains non-null conflicting supplier assignments, and reports ambiguous/missing links. `php artisan suppliers:backfill-products` can be repeated; conflicts return a nonzero exit code for review. The migration logs conflicts rather than silently selecting an owner.

## Acceptance coverage

| Instruction cases | Verification |
| --- | --- |
| 1–7 manual supplier/product creation, Own Inventory, persistence, supplier change without stock/price/SKU/image changes, combined filters/pagination | `ProductSupplierWorkflowTest`, actual local browser create/save/retrieve, `ProductSupplierForm.test.mjs` |
| 8–12 independent navigation, CRUD/deactivation, no unsafe deletion, product counts | `SupplierManagementTest`, `SupplierPages.test.mjs` |
| 13–18 import, automatic ownership, duplicate prevention, re-sync ownership, pricing/images/category/variation preservation | `SupplierOwnershipWorkflowTest`, existing `ProductImportServiceTest`, `ProductPricingWorkflowTest`, `PriceStockSyncServiceTest`, catalog/category/variation suites |
| 19 cancel and controlled relink | `SupplierOwnershipWorkflowTest`, existing sync job/batch tests |
| 20 existing API contracts/authentication | existing Mohasagor/configurable client, registry, mirror and connection tests; API client source diff is empty |
| 21–24 receipt/history attribution, supplier editing and stock separation | `SupplierInventoryTrackingTest`, `OrderInventoryTrackingTest`, existing inventory suites |
| 25–27 additive schema, validated old mappings, repeatable backfill and conflict preservation | fresh isolated SQLite migrations and `SupplierOwnershipWorkflowTest` |

Additional order tests cover actual guest checkout, quick order, campaign, admin-created, add-to-order and recovered-cart reservations; cancellation restores the originally reserved supplier reference, prevents duplicate releases, and does not invent units for partial/zero campaign reservations.

Local browser evidence: `supplier-review/supplier-details-local.png`. A new supplier was created inside an unsaved product form, the product was saved/retrieved, and Supplier Details showed one Manual Product and its Opening Stock reference.

## Validation results

- Full PHP suite: **372 tests, 371 passed, 3408 assertions, 1 existing storefront SEO failure** (`StorefrontSeoTest::test_paginated_catalogue_is_indexable_self_canonical_and_has_crawlable_links`, line 34). No supplier/inventory/order/integration test fails. The original run was 319 tests; this review adds 53 PHP workflow cases.
- JavaScript component suite: **49 passed, 0 failed**, including 6 new supplier form/page/navigation rendering cases.
- Frontend production build: passed; existing template-font resolution warnings only.
- Changed/new PHP files: syntax checks passed. Route listing verifies all eight supplier routes, dedicated relink route, and global inventory history/export routes. `git diff --check` passed.
- Fresh isolated local SQLite database: all migrations applied successfully.
- Upgrade rehearsal from the pre-000003 schema: three existing product records retained name, slug, SKU, regular/sale prices, and stock; two supplier rows remained two; the valid old import received its supplier; manual assignment remained intact; a conflicting import remained unchanged and was reported. Repeating backfill made zero updates and reported the same conflict.
- Browser smoke test: inline supplier creation preserved the draft name/category; product saved and retrieved with its supplier and quantity 10; Supplier Details displayed one manual product and one Opening Stock reference, with creator attribution.

## Deployment and rollback procedure

1. Obtain the user's explicit live-deployment confirmation. Keep commits local until then.
2. Inspect the current production commit/state and supplier/import mappings. Record product/import/supplier counts and unresolved ownership conflicts. Do not merge suppliers by display name.
3. Pause import workers during the upgrade so old/new sync code cannot race the backfill. Take a private database backup using the existing `store:backup`/System Health backup facility. Confirm its completed record, readable archive and private location before migrations. Preserve current uploaded media and environment configuration.
4. Rehearse the migration/backfill against a staging copy using the production database engine. SQLite tests do not replace this MySQL compatibility check. Stop on unexpected constraints or unresolved ownership conflicts.
5. Deploy the reviewed code/build, run migrations and inspect the backfill report. Assign the new independent supplier permissions deliberately to authorized staff. Restart workers with the new code.
6. Check live supplier list/create/details/report, manual product editing, opening/receipt history, integration connection, one approved import/re-sync, cancellation/relink and supplier availability. Use a draft import and retain its real external mapping.
7. Prefer a corrective forward deployment for an application issue. If code must be rolled back, stop workers and use the recorded previous commit while retaining additive columns. Do not run destructive migration downs or restore an old database over new orders. Database restoration requires a maintenance window and an explicit plan to preserve post-backup transactions.

## Remaining limits

- Production data inspection, private production backup, production-engine staging rehearsal and live-provider smoke tests await deployment authorization/access. Local provider tests use controlled responses; no live supplier import was performed during this review.
- Historical supplier/actor references that were never stored cannot be reconstructed reliably. Old inventory history remains honest; old orders keep the prior cancellation quantity behavior because their actual reservation amount was not recorded.
- API availability updates remain in Sync Runs. They are not mislabeled as local warehouse receipts.
- One storefront SEO pagination fallback test was already failing in the earlier implementation run. Its relevant storefront controller/view/service files are unchanged by this supplier work; this is reported separately rather than counted as a passing suite.
- Existing unresolved template-font build warnings remain.

Live deployment remains pending confirmation.
