<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;

/** One inventory for the role editor, navigation and server authorization. */
class AdminPermissions
{
    public const ACTIONS = ['view' => 'View', 'add' => 'Add', 'edit' => 'Edit', 'delete' => 'Delete', 'execute' => 'Execute'];

    public const MODULES = [
        'dashboard' => ['Dashboard', ['view']],
        'analytics' => ['Analytics & Reports', ['view', 'execute']],
        'products' => ['Products', ['view', 'add', 'edit', 'delete']],
        'inventory' => ['Inventory / Stock', ['view', 'edit', 'execute']],
        'stock_history' => ['Stock History', ['view', 'execute']],
        'categories' => ['Categories & Subcategories', ['view', 'add', 'edit', 'delete']],
        'menu_ordering' => ['Navbar & Menu Ordering', ['view', 'edit']],
        'sku_configuration' => ['SKU Configuration', ['view', 'edit', 'execute']],
        'suppliers' => ['Suppliers', ['view', 'add', 'edit']],
        'supplier_reports' => ['Supplier Reports', ['view', 'execute']],
        'orders' => ['Orders', ['view', 'add', 'edit', 'delete', 'execute']],
        'invoices' => ['Invoices / Print / PDF', ['view', 'execute']],
        'abandoned_carts' => ['Abandoned Carts', ['view', 'edit', 'delete', 'execute']],
        'customers' => ['Customer CRM', ['view', 'add', 'edit', 'delete', 'execute']],
        'reviews' => ['Customer Reviews', ['view', 'delete', 'execute']],
        'banners' => ['Banners & Sliders', ['view', 'add', 'edit', 'delete']],
        'landing_pages' => ['Landing Pages', ['view', 'add', 'edit', 'delete']],
        'flash_sale' => ['Flash Sale', ['view', 'add', 'edit', 'delete']],
        'popup' => ['Popup & Announcement', ['view', 'edit']],
        'features' => ['Features & Badges', ['view', 'add', 'edit', 'delete']],
        'product_cta' => ['Quick Order & CTA Buttons', ['view', 'edit']],
        'contact_fields' => ['Contact Form Fields', ['view', 'add', 'edit', 'delete']],
        'coupons' => ['Coupons & Discounts', ['view', 'add', 'edit', 'delete']],
        'messages' => ['Contact Messages', ['view', 'edit', 'delete']],
        'dropshipping' => ['Dropshipping Overview', ['view']],
        'supplier_connections' => ['Dropshipping / Supplier Connection & Driver Profiles', ['view', 'add', 'edit', 'execute']],
        'pricing_rules' => ['Dropshipping / Pricing Rules', ['view', 'edit', 'execute']],
        'category_mapping' => ['Dropshipping / Category Mapping', ['view', 'edit']],
        'supplier_products' => ['Dropshipping / Supplier Products', ['view', 'edit', 'execute']],
        'imported_products' => ['Dropshipping / Imported Products', ['view', 'edit', 'execute']],
        'sync_runs' => ['Dropshipping / Sync Runs', ['view', 'execute']],
        'variation_mapping' => ['Dropshipping / Variation Mapping', ['view', 'edit', 'execute']],
        'media_cleanup' => ['Dropshipping / Media Cleanup', ['view']],
        'storage_usage' => ['Dropshipping / Storage Usage', ['view']],
        'database_migration' => ['Dropshipping / Database Migration', ['view']],
        'media' => ['Media Manager', ['view', 'add', 'delete', 'execute']],
        'system_health' => ['System Health', ['view', 'execute']],
        'backups' => ['Database / Media / Full Backups', ['view', 'add', 'delete', 'execute']],
        'optimized_images' => ['Optimized Images', ['view', 'delete']],
        'recovery_trash' => ['Recovery Trash', ['view', 'delete', 'execute']],
        'git_repository' => ['Git Repository / Deploy / Rollback', ['view', 'edit', 'execute']],
        'fraud_guard' => ['Fraud Guard / Modules / Limits / Courier', ['view', 'edit', 'execute']],
        'blocked_ips' => ['Blocked IPs', ['view', 'add', 'delete']],
        'blocked_devices' => ['Blocked Devices', ['view', 'add', 'delete']],
        'blocked_phones' => ['Blocked Phones', ['view', 'add', 'delete']],
        'admins' => ['Users', ['view', 'add', 'edit', 'delete']],
        'roles' => ['Roles & Permissions (Super Admin only)', ['view', 'add', 'edit', 'delete']],
        'settings' => ['Store Settings / Payments / Shipping / SEO / Tracking', ['view', 'edit', 'execute']],
        'seo_completion' => ['Product SEO Completion', ['view', 'execute']],
        'translation_settings' => ['Translation API Settings', ['view', 'edit', 'execute']],
    ];

    private const RESOURCES = [
        'ProductController' => 'products', 'CategoryController' => 'categories',
        'SupplierController' => 'suppliers', 'CustomerController' => 'customers',
        'BannerController' => 'banners', 'LandingPageController' => 'landing_pages',
        'FeatureController' => 'features', 'CouponController' => 'coupons',
        'ContactFormFieldController' => 'contact_fields', 'ContactMessageController' => 'messages',
        'BlockedIpController' => 'blocked_ips', 'BlockedDeviceController' => 'blocked_devices',
        'BlockedPhoneController' => 'blocked_phones', 'AdminUserController' => 'admins',
        'RoleController' => 'roles', 'MediaController' => 'media', 'OrderController' => 'orders',
        'ReviewController' => 'reviews',
    ];

    /** Explicit mappings for non-CRUD operations. No HTTP-method guessing for execution endpoints. */
    private const OPERATIONS = [
        'DashboardController' => ['index' => 'dashboard.view'],
        'AnalyticsController' => ['index' => 'analytics.view'],
        'InventoryController' => [
            'index' => 'inventory.view', 'adjust' => 'inventory.edit', 'updateStock' => 'inventory.edit',
            'resume' => 'inventory.execute', 'export' => 'inventory.execute',
            'history' => 'stock_history.view', 'movements' => 'stock_history.view', 'exportHistory' => 'stock_history.execute',
        ],
        'SkuConfigurationController' => ['index' => 'sku_configuration.view', 'update' => 'sku_configuration.edit', 'preview' => 'sku_configuration.execute', 'apply' => 'sku_configuration.execute'],
        'SupplierController' => ['report' => 'supplier_reports.view'],
        'CategoryController' => ['image' => 'categories.view', 'ordering' => 'menu_ordering.view', 'saveOrdering' => 'menu_ordering.edit'],
        'BannerController' => ['bulkStatus' => 'banners.edit', 'bulkPosition' => 'banners.edit', 'bulkDelete' => 'banners.delete'],
        'LandingPageController' => ['previewDesign' => 'landing_pages.view', 'toggleDesign' => 'landing_pages.edit', 'updateSection' => 'landing_pages.edit'],
        'FlashSaleController' => ['index' => 'flash_sale.view', 'add' => 'flash_sale.add', 'remove' => 'flash_sale.delete', 'reorder' => 'flash_sale.edit', 'updateEndsAt' => 'flash_sale.edit'],
        'AdminPopupController' => ['index' => 'popup.view', 'update' => 'popup.edit'],
        'ProductCtaController' => ['edit' => 'product_cta.view', 'update' => 'product_cta.edit'],
        'ContactMessageController' => ['markRead' => 'messages.edit', 'archive' => 'messages.edit'],
        'CustomerController' => ['profile' => 'customers.view', 'note' => 'customers.add', 'export' => 'customers.execute'],
        'OrderController' => [
            'productSearch' => ['orders.add', 'orders.edit'], 'customerHistory' => ['orders.add', 'orders.view'],
            'addItems' => 'orders.edit', 'updateItemDiscount' => 'orders.edit', 'verify' => 'orders.execute',
            'reject' => 'orders.execute', 'checkFraud' => 'orders.execute',
        ],
        'CourierController' => ['sendToCourier' => 'orders.execute'],
        'OrderSourcingController' => ['store' => 'orders.execute'],
        'AdminInvoiceController' => ['show' => 'invoices.view', 'pdf' => 'invoices.execute', 'bulk' => 'invoices.execute'],
        'AbandonedCheckoutController' => ['index' => 'abandoned_carts.view', 'notifications' => 'abandoned_carts.view', 'updateNote' => 'abandoned_carts.edit', 'recover' => 'abandoned_carts.edit', 'destroy' => 'abandoned_carts.delete', 'createOrder' => 'abandoned_carts.execute'],
        'ReviewController' => ['approve' => 'reviews.execute', 'reject' => 'reviews.execute'],
        'MediaController' => ['createBanners' => 'media.execute'],
        'FakeOrderGuardController' => ['index' => 'fraud_guard.view', 'checkPhone' => 'fraud_guard.execute', 'testConnection' => 'fraud_guard.execute', 'planInfo' => 'fraud_guard.view'],
        'OptimizedImageController' => ['index' => 'optimized_images.view', 'delete' => 'optimized_images.delete', 'trash' => 'recovery_trash.view', 'previewTrash' => 'recovery_trash.view', 'restoreTrash' => 'recovery_trash.execute', 'purgeTrash' => 'recovery_trash.delete'],
        'SystemHealthController' => [
            'index' => 'system_health.view', 'createBackup' => 'backups.add', 'createMediaBackup' => 'backups.add', 'createFullBackup' => 'backups.add',
            'download' => 'backups.view', 'destroy' => 'backups.delete', 'restoreDatabase' => 'backups.execute',
            'restoreMedia' => 'backups.execute', 'restoreFull' => 'backups.execute', 'clearCache' => 'system_health.execute', 'runMigrations' => 'system_health.execute',
        ],
        'ApplicationUpdateController' => [
            'index' => 'git_repository.view', 'saveSettings' => 'git_repository.edit', 'test' => 'git_repository.execute',
            'check' => 'git_repository.execute', 'pull' => 'git_repository.execute', 'deploy' => 'git_repository.execute',
            'discardAndDeploy' => 'git_repository.execute', 'resetToRemote' => 'git_repository.execute', 'rollback' => 'git_repository.execute',
        ],
        'SettingController' => ['edit' => 'settings.view', 'updateSection' => 'settings.edit', 'testMail' => 'settings.execute'],
        'SeoCompletionController' => ['index' => 'seo_completion.view', 'translate' => 'seo_completion.execute', 'store' => 'seo_completion.execute'],
        'TranslationSettingsController' => ['index' => 'translation_settings.view', 'update' => 'translation_settings.edit', 'test' => 'translation_settings.execute'],
        'DropshippingController' => [
            'index' => 'dropshipping.view', 'apiSettings' => 'supplier_connections.view',
            'store' => 'supplier_connections.add', 'update' => 'supplier_connections.edit', 'toggle' => 'supplier_connections.edit',
            'storeDriverProfile' => 'supplier_connections.add', 'updateDriverProfile' => 'supplier_connections.edit', 'toggleDriverProfile' => 'supplier_connections.edit', 'testConnection' => 'supplier_connections.execute',
            'pricingRules' => 'pricing_rules.view', 'updatePricing' => 'pricing_rules.edit', 'updatePriceMapping' => 'pricing_rules.edit', 'previewPricing' => 'pricing_rules.execute',
            'categoryMapping' => 'category_mapping.view', 'mapCategory' => 'category_mapping.edit', 'uploadCategoryImage' => 'category_mapping.edit',
            'supplierProducts' => 'supplier_products.view', 'importSupplierProduct' => 'supplier_products.execute', 'bulkImportSupplierProducts' => 'supplier_products.execute', 'relinkSupplierProduct' => 'supplier_products.edit',
            'importedProducts' => 'imported_products.view', 'publishImportedProduct' => 'imported_products.edit', 'bulkPublishImportedProducts' => 'imported_products.edit', 'bulkUnpublishImportedProducts' => 'imported_products.edit', 'bulkUpdateImportedProductPrices' => 'imported_products.edit', 'restoreImportedProductPrices' => 'imported_products.edit', 'bulkSyncImportedProducts' => 'imported_products.execute', 'bulkSyncFilteredImportedProducts' => 'imported_products.execute',
            'syncRuns' => 'sync_runs.view', 'runDetails' => 'sync_runs.view', 'activeRun' => 'sync_runs.view', 'catalogProgress' => 'sync_runs.view',
            'catalog' => 'sync_runs.execute', 'priceStock' => 'sync_runs.execute', 'workCatalogQueue' => 'sync_runs.execute', 'cancelRun' => 'sync_runs.execute', 'pauseCatalogRun' => 'sync_runs.execute', 'resumeCatalogRun' => 'sync_runs.execute', 'retryFailedImportedProductSync' => 'sync_runs.execute', 'workImportedProductQueue' => 'sync_runs.execute',
            'variationMapping' => 'variation_mapping.view', 'mapVariation' => 'variation_mapping.edit', 'autoMapVariations' => 'variation_mapping.execute',
            'mediaCleanup' => 'media_cleanup.view', 'storageUsage' => 'storage_usage.view', 'databaseMigration' => 'database_migration.view',
        ],
    ];

    public static function groups(): array
    {
        $groups = [];
        foreach (self::MODULES as $module => [$label, $actions]) {
            $items = [];
            foreach ($actions as $action) {
                $items[$module.'.'.$action] = self::ACTIONS[$action];
            }
            $hint = match ($module) {
                'orders' => 'Execute: payment verification/rejection, courier dispatch and fraud checks.',
                'customers' => 'Add: private customer notes. Execute: CSV export.',
                'backups' => 'Add: create backups. View: download. Execute: restore backups.',
                'git_repository' => 'Execute: connection checks, fetch, deploy, reset and rollback.',
                'system_health' => 'Execute: clear caches and run database migrations.',
                'recovery_trash' => 'Execute: restore files. Delete: permanently purge expired batches.',
                'reviews' => 'Execute: approve or reject real reviews.',
                'translation_settings' => 'Edit: choose the translation provider and manage its API key. Execute: test the saved provider.',
                'supplier_products' => 'Execute: import selected supplier products.',
                'sync_runs' => 'Execute: start, pause, resume, cancel, retry and work sync queues.',
                'media' => 'Execute: create banners from selected media.',
                'abandoned_carts' => 'Execute: create an order; also requires Orders / Add.',
                default => null,
            };
            $groups[$module] = ['label' => $label, 'items' => $items, 'hint' => $hint, 'super_admin_only' => $module === 'roles'];
        }
        return $groups;
    }

    public static function keys(): array
    {
        return array_merge(...array_values(array_map(fn ($group) => array_keys($group['items']), self::groups())));
    }

    /** Expand saved legacy grants; new role submissions use the individual actions only. */
    public static function expand(array $permissions): array
    {
        $aliases = [
            'products.create' => ['products.add', 'products.edit', 'inventory.edit', 'inventory.execute'],
            'orders.manage' => ['orders.add', 'orders.edit', 'orders.execute', 'invoices.view', 'invoices.execute'],
            'customers.manage' => ['customers', 'abandoned_carts'], 'reviews.manage' => ['reviews'],
            'catalog.manage' => ['categories', 'menu_ordering', 'banners', 'features'],
            'marketing.manage' => ['coupons', 'flash_sale', 'landing_pages', 'popup'],
            'messages.manage' => ['messages'], 'suppliers.manage' => ['suppliers', 'supplier_reports'],
            'dropshipping.manage' => ['dropshipping', 'supplier_connections', 'pricing_rules', 'category_mapping', 'supplier_products', 'imported_products', 'sync_runs', 'variation_mapping', 'media_cleanup', 'storage_usage', 'database_migration'],
            'media.manage' => ['media'], 'fraud_guard.manage' => ['fraud_guard', 'blocked_ips', 'blocked_devices', 'blocked_phones'],
            'admins.manage' => ['admins'],
            'settings.manage' => ['settings', 'sku_configuration', 'product_cta', 'contact_fields', 'system_health', 'backups', 'optimized_images', 'recovery_trash', 'git_repository', 'seo_completion'],
        ];
        $expanded = $permissions;
        foreach ($permissions as $permission) {
            foreach ($aliases[$permission] ?? [] as $grant) {
                if (isset(self::MODULES[$grant])) {
                    foreach (self::MODULES[$grant][1] as $action) $expanded[] = $grant.'.'.$action;
                } else {
                    $expanded[] = $grant;
                }
            }
        }
        return array_values(array_unique($expanded));
    }

    /** Canonical saved permissions allow an individual legacy-implied action to be revoked. */
    public static function canonical(array $permissions): array
    {
        return array_values(array_intersect(self::expand($permissions), self::keys()));
    }

    public static function migrateLegacy(array $permissions): array
    {
        $expanded = self::canonical($permissions);
        if (in_array('products.view', $permissions, true)) array_push($expanded, 'inventory.view', 'stock_history.view');
        if (in_array('suppliers.view', $permissions, true)) $expanded[] = 'supplier_reports.view';
        // These two pages were previously available to every staff account.
        array_push($expanded, 'dashboard.view', 'analytics.view');
        return array_values(array_unique($expanded));
    }

    public static function landingUrl(User $user): string
    {
        if ($user->isSuperAdmin() || $user->hasPermission('dashboard.view')) return route('admin.dashboard');
        foreach (app('router')->getRoutes() as $route) {
            if (! in_array('admin', $route->gatherMiddleware(), true) || ! in_array('GET', $route->methods(), true)
                || str_contains($route->uri(), '{')) continue;
            $permissions = self::forRoute($route);
            if ($permissions && collect($permissions)->contains(fn ($permission) => str_ends_with($permission, '.view')
                && ! str_starts_with($permission, 'roles.') && $user->hasPermission($permission))) {
                return url('/'.$route->uri());
            }
        }
        return route('admin.admins.password.edit');
    }

    /** @return list<string>|null Null means no mapping: fail closed for protected routes. */
    public static function forRoute(Route $route, ?Request $request = null): ?array
    {
        if ($route->uri() === 'admin/dropshipping/sku-configuration') return ['sku_configuration.view'];
        [$controller, $method] = array_pad(explode('@', $route->getActionName(), 2), 2, '');
        $controller = class_basename($controller);
        if ($controller === 'SettingController' && $method === 'updateSection' && $request && $route->parameter('section') === 'fake_order_guard') return ['fraud_guard.edit'];
        if ($controller === 'SupplierController' && $method === 'report' && $request?->input('format') === 'csv') return ['supplier_reports.execute'];
        if ($controller === 'AdminUserController' && in_array($method, ['editPassword', 'updatePassword'], true)) return [];
        $permission = self::OPERATIONS[$controller][$method] ?? null;
        if ($permission !== null) return (array) $permission;

        $module = self::RESOURCES[$controller] ?? null;
        if ($method === 'bulk' && ($module || $controller === 'FlashSaleController')) {
            $action = $request?->input('bulk_action');
            if ($controller === 'AdminInvoiceController') return ['invoices.execute'];
            if ($controller === 'FlashSaleController') return [$action === 'remove' ? 'flash_sale.delete' : 'flash_sale.add'];
            return [$module.'.'.match (true) {
                $action === 'delete' => 'delete',
                $module === 'orders' || $module === 'reviews' => 'execute',
                default => 'edit',
            }];
        }
        $action = match ($method) {
            'index', 'show' => 'view', 'create', 'store' => 'add', 'edit', 'update', 'toggle' => 'edit',
            'destroy', 'destroyImage' => 'delete', default => null,
        };
        return $module && $action && in_array($action, self::MODULES[$module][1], true) ? [$module.'.'.$action] : null;
    }
}
