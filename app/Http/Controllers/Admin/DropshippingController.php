<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\Dropshipping\StartSupplierCatalogSync;
use App\Jobs\Dropshipping\StartSupplierPriceStockSync;
use App\Jobs\Dropshipping\SyncSupplierCatalogPage;
use App\Jobs\Dropshipping\TestSupplierConnection;
use App\Models\DropshipSupplier;
use App\Models\DropshipDriverProfile;
use App\Models\DropshipSupplierCategory;
use App\Models\DropshipSupplierProduct;
use App\Models\DropshipSupplierVariant;
use App\Models\DropshipSyncRun;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Services\Dropshipping\CategoryMapper;
use App\Services\Dropshipping\ProductImportService;
use App\Services\Dropshipping\ProductImportResult;
use App\Services\Dropshipping\PricingEngine;
use App\Services\Dropshipping\Support\SyncRunState;
use App\Services\Dropshipping\SyncRunService;
use App\Services\Dropshipping\SupplierRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Illuminate\Validation\ValidationException;
use Throwable;

class DropshippingController extends Controller
{
    public function index(SupplierRegistry $registry)
    {
        $suppliers = DropshipSupplier::query()
            ->withCount([
                'products', 'syncRuns', 'categories', 'categoryMappings', 'variants',
                'variants as mapped_variants_count' => fn ($query) => $query->whereHas('variantLink'),
            ])
            ->latest('id')
            ->get()
            ->map(fn (DropshipSupplier $supplier): array => [
                'id' => $supplier->id,
                'key' => $supplier->key,
                'name' => $supplier->name,
                'driver_key' => $supplier->driver_key,
                'base_url' => $supplier->base_url,
                'is_active' => $supplier->is_active,
                'last_connection_status' => $supplier->last_connection_status,
                'last_connection_message' => $supplier->last_connection_message,
                'last_connection_tested_at' => $supplier->last_connection_tested_at?->toIso8601String(),
                'last_connection_success_at' => $supplier->last_connection_success_at?->toIso8601String(),
                'capabilities' => $supplier->capabilities ?? [],
                'price_field_mapping' => $supplier->price_field_mapping ?? [],
                'pricing_rules' => $supplier->pricing_rules ?? [],
                'categories_count' => $supplier->categories_count,
                'category_mappings_count' => $supplier->category_mappings_count,
                'variants_count' => $supplier->variants_count,
                'mapped_variants_count' => $supplier->mapped_variants_count,
                'products_count' => $supplier->products_count,
                'sync_runs_count' => $supplier->sync_runs_count,
            ])
            ->values();

        $runs = DropshipSyncRun::query()
            ->with('supplier:id,name,key')
            ->latest('id')
            ->limit(30)
            ->get()
            ->map(fn (DropshipSyncRun $run): array => [
                'id' => $run->id,
                'supplier' => $run->supplier ? ['name' => $run->supplier->name, 'key' => $run->supplier->key] : null,
                'type' => $run->type,
                'status' => $run->status,
                'total_items' => $run->total_items,
                'processed_items' => $run->processed_items,
                'success_items' => $run->success_items,
                'failed_items' => $run->failed_items,
                'started_at' => $run->started_at?->toIso8601String(),
                'finished_at' => $run->finished_at?->toIso8601String(),
            ])
            ->values();

        return Inertia::render('Admin/Dropshipping/Index', [
            'suppliers' => $suppliers,
            'runs' => $runs,
            'available_drivers' => $registry->driverKeys(),
        ]);
    }

    public function supplierProducts(Request $request, PricingEngine $pricing)
    {
        $stockFilter = $request->string('stock')->toString();
        $stockOperator = $request->string('stock_operator')->toString();
        $stockValue = $request->input('stock_value');
        $categoryFilter = $request->string('category')->toString();
        $importStatusFilter = $request->string('import_status')->toString();
        
        $products = DropshipSupplierProduct::query()
            ->when($stockFilter === 'positive', fn ($query) => $query->where('stock_qty', '>', 0))
            ->when($categoryFilter !== '', fn ($query) => $query->where('supplier_category_key', $categoryFilter))
            ->when(in_array($stockOperator, ['gt', 'lt', 'eq'], true) && is_numeric($stockValue), function ($query) use ($stockOperator, $stockValue) {
                $operator = ['gt' => '>', 'lt' => '<', 'eq' => '='][$stockOperator];
                $query->where('stock_qty', $operator, (float) $stockValue);
            })
            ->when($importStatusFilter === 'imported', fn ($query) => $query->has('productLink'))
            ->when($importStatusFilter === 'not_imported', fn ($query) => $query->doesntHave('productLink'))
            ->with(['supplier:id,name,key,pricing_rules', 'productLink.product:id,name,is_published'])
            ->latest('id')
            ->paginate(100)
            ->withQueryString();

        $productRows = $products->getCollection()
            ->map(function (DropshipSupplierProduct $product) use ($pricing): array {
                $price = $pricing->calculate(
                    $product->cost_price,
                    $product->max_price,
                    $product->supplier->pricing_rules ?? []
                );
                
                return [
                    'id' => $product->id,
                    'supplier' => $product->supplier ? ['name' => $product->supplier->name, 'key' => $product->supplier->key] : null,
                'supplier_product_id' => $product->supplier_product_id,
                'name' => $product->name,
                'currency' => $product->currency,
                'cost_price' => $product->cost_price,
                'max_price' => $product->max_price,
                'calculated_selling' => $price->rawSellingPrice ?? $price->targetSellingPrice,
                'calculated_minimum' => $price->calculatedMinimumPrice ?? $price->minimumPrice,
                'calculated_maximum' => $price->calculatedMaximumPrice ?? $price->ceilingPrice,
                'calculated_discounted' => $price->finalSalePrice,
                'stock_qty' => $product->stock_qty,
                'is_available' => $product->is_available,
                'linked_product' => $product->productLink?->product ? [
                    'id' => $product->productLink->product->id,
                    'name' => $product->productLink->product->name,
                    'is_published' => $product->productLink->product->is_published,
                ] : null,
                'fetched_at' => $product->fetched_at?->toIso8601String(),
            ];
        })->values();

        return Inertia::render('Admin/Dropshipping/SupplierProducts', [
            'products' => $productRows,
            'stock_filter' => $stockFilter === 'positive' ? 'positive' : 'all',
            'stock_operator' => in_array($stockOperator, ['gt', 'lt', 'eq'], true) ? $stockOperator : '',
            'stock_value' => is_numeric($stockValue) ? (string) $stockValue : '',
            'category_filter' => $categoryFilter,
            'import_status_filter' => in_array($importStatusFilter, ['imported', 'not_imported']) ? $importStatusFilter : '',
            'categories' => DropshipSupplierProduct::query()->whereNotNull('supplier_category_key')->distinct()->orderBy('supplier_category_key')->pluck('supplier_category_key')->values(),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'total' => $products->total(),
            ],
            'suppliers' => DropshipSupplier::query()
                ->withCount(['products', 'variants'])
                ->latest('id')
                ->get(['id', 'name', 'key', 'is_active', 'last_connection_status']),
        ]);
    }

    public function bulkImportSupplierProducts(Request $request, ProductImportService $imports)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer', 'distinct', 'exists:dropship_supplier_products,id'],
        ]);

        $imported = 0;
        $alreadyLinked = 0;
        $blocked = 0;

        foreach (DropshipSupplierProduct::query()->whereIn('id', $data['ids'])->get() as $supplierProduct) {
            $result = $imports->import($supplierProduct);
            $imported += $result->status === ProductImportResult::IMPORTED ? 1 : 0;
            $alreadyLinked += $result->status === ProductImportResult::ALREADY_LINKED ? 1 : 0;
            $blocked += $result->status === ProductImportResult::BLOCKED ? 1 : 0;
        }

        return back()->with('status', "Batch import complete: {$imported} imported, {$alreadyLinked} already linked, {$blocked} blocked for review.");
    }

    public function importedProducts(Request $request)
    {
        $categoryFilter = $request->string('category')->toString();
        $statusFilter = $request->string('status')->toString();
        $search = trim($request->string('q')->toString());
        $requestedPerPage = $request->input('per_page', 100);
        $perPage = is_numeric($requestedPerPage) && in_array((int) $requestedPerPage, [25, 50, 100], true)
            ? (int) $requestedPerPage
            : 100;
        
        $products = Product::query()
            ->whereHas('supplierLinks.supplierProduct', fn ($query) => $query->when($categoryFilter !== '', fn ($categoryQuery) => $categoryQuery->where('supplier_category_key', $categoryFilter)))
            ->when($search !== '', fn ($query) => $query->where(function ($searchQuery) use ($search) {
                $searchQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            }))
            ->when($statusFilter === 'published', fn ($query) => $query->where('is_published', true))
            ->when($statusFilter === 'draft', fn ($query) => $query->where('is_published', false))
            ->with([
                'images' => fn ($query) => $query
                    ->select(['id', 'product_id', 'path', 'alt', 'is_primary', 'position'])
                    ->orderByDesc('is_primary')
                    ->orderBy('position'),
                'supplierLinks.supplierProduct.supplier:id,name,key',
                'supplierLinks.supplierProduct.variants.variantLink',
            ])
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        $productRows = $products->getCollection()
            ->map(function (Product $product): array {
                $primaryImage = $product->images->first();

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'image_url' => $primaryImage?->url(),
                    'image_alt' => $primaryImage?->alt ?: $product->name,
                    'image_count' => $product->images->count(),
                    'is_published' => $product->is_published,
                    'regular_price' => $product->regular_price,
                    'sale_price' => $product->sale_price,
                    'stock_quantity' => $product->stock_quantity,
                    'pricing' => $product->supplierLinks->first()?->pricing_snapshot,
                    'category' => $product->supplierLinks->first()?->supplierProduct?->supplier_category_key,
                    'supplier' => $product->supplierLinks->first()?->supplierProduct?->supplier?->name,
                    'supplier_variants' => $product->supplierLinks->first()?->supplierProduct?->variants->count() ?? 0,
                    'mapped_variants' => $product->supplierLinks->first()?->supplierProduct?->variants->filter(fn ($variant) => $variant->variantLink !== null)->count() ?? 0,
                ];
            })
            ->values();

        return Inertia::render('Admin/Dropshipping/ImportedProducts', [
            'products' => $productRows,
            'search_filter' => $search,
            'category_filter' => $categoryFilter,
            'status_filter' => $statusFilter,
            'categories' => DropshipSupplierProduct::query()
                ->whereNotNull('supplier_category_key')
                ->distinct()
                ->orderBy('supplier_category_key')
                ->pluck('supplier_category_key')
                ->values(),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    public function mediaCleanup()
    {
        $linkedProductIds = DropshipProductLink::query()
            ->whereNotNull('product_id')
            ->select('product_id');

        $linkedImages = ProductImage::query()->whereIn('product_id', $linkedProductIds);
        $externalImages = (clone $linkedImages)->where(function ($query) {
            $query->where('path', 'like', 'http://%')
                ->orWhere('path', 'like', 'https://%');
        });

        return Inertia::render('Admin/Dropshipping/MediaCleanup', [
            'audit' => [
                'supplier_products' => DropshipSupplierProduct::query()->whereHas('productLink')->count(),
                'supplier_image_references' => $linkedImages->count(),
                'external_image_references' => $externalImages->count(),
                'local_image_references' => (clone $linkedImages)
                    ->where(function ($query) {
                        $query->where('path', 'not like', 'http://%')
                            ->where('path', 'not like', 'https://%');
                    })
                    ->count(),
                'unlinked_image_references' => ProductImage::query()
                    ->whereDoesntHave('product.supplierLinks')
                    ->count(),
            ],
        ]);
    }

    public function storageUsage()
    {
        return Inertia::render('Admin/Dropshipping/StorageUsage', [
            'suppliers' => DropshipSupplier::query()->withCount(['products', 'categories'])->get(['id', 'name', 'key']),
            'products' => DropshipSupplierProduct::query()->count(),
            'variants' => DropshipSupplierVariant::query()->count(),
            'categories' => DropshipSupplierCategory::query()->count(),
        ]);
    }

    public function databaseMigration()
    {
        $tables = collect([
            'dropship_driver_profiles',
            'dropship_suppliers',
            'dropship_supplier_categories',
            'dropship_category_mappings',
            'dropship_supplier_products',
            'dropship_supplier_variants',
            'dropship_product_links',
            'dropship_variant_links',
            'dropship_sync_runs',
            'dropship_sync_run_items',
        ])->map(fn (string $table): array => ['table' => $table, 'exists' => Schema::hasTable($table)])->values();

        return Inertia::render('Admin/Dropshipping/DatabaseMigration', ['tables' => $tables]);
    }

    public function categoryMapping()
    {
        $profiles = DropshipDriverProfile::query()->get(['key', 'categories_path']);
        $mappings = DropshipSupplierCategory::query()
            ->with(['supplier:id,name,key', 'supplier.categoryMappings.category:id,name'])
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(function (DropshipSupplierCategory $category): array {
                $mapping = $category->supplier?->categoryMappings?->firstWhere('supplier_category_key', $category->supplier_category_key);

                return [
                    'id' => $category->id,
                    'supplier' => $category->supplier?->name,
                    'key' => $category->supplier_category_key,
                    'name' => $category->name,
                    'path' => $category->path,
                    'image' => $category->image ? \Illuminate\Support\Facades\Storage::url($category->image) : null,
                    'local_category' => $mapping?->category?->name,
                ];
            })
            ->values();

        return Inertia::render('Admin/Dropshipping/CategoryMapping', [
            'mappings' => $mappings,
            'local_categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'suppliers' => DropshipSupplier::query()
                ->withCount(['categories', 'categoryMappings'])
                ->latest('id')
                ->get(['id', 'name', 'key', 'driver_key', 'is_active', 'last_connection_status'])
                ->map(function (DropshipSupplier $supplier) use ($profiles): array {
                    return [
                        'id' => $supplier->id,
                        'name' => $supplier->name,
                        'key' => $supplier->key,
                        'driver_key' => $supplier->driver_key,
                        'is_active' => $supplier->is_active,
                        'last_connection_status' => $supplier->last_connection_status,
                        'categories_count' => $supplier->categories_count,
                        'category_endpoint' => $profiles->firstWhere('key', $supplier->driver_key)?->categories_path,
                    ];
                })->values(),
        ]);
    }

    public function variationMapping(Request $request)
    {
        $variants = DropshipSupplierVariant::query()
            ->with(['supplierProduct.supplier:id,name,key', 'supplierProduct.productLink.product:id,name'])
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        $variantRows = $variants->getCollection()
            ->map(fn (DropshipSupplierVariant $variant): array => [
                'id' => $variant->id,
                'supplier' => $variant->supplierProduct?->supplier?->name,
                'product' => $variant->supplierProduct?->name,
                'variant_id' => $variant->supplier_variant_id,
                'sku' => $variant->sku,
                'attributes' => $variant->attributes ?? [],
                'stock_qty' => $variant->stock_qty,
                'local_product_id' => $variant->supplierProduct?->productLink?->product_id,
                'local_product_name' => $variant->supplierProduct?->productLink?->product?->name,
                'linked' => $variant->variantLink()->whereNotNull('product_variant_id')->exists(),
            ])
            ->values();

        $productIds = $variantRows->pluck('local_product_id')->filter()->unique()->values();

        return Inertia::render('Admin/Dropshipping/VariationMapping', [
            'variants' => $variantRows,
            'pagination' => [
                'current_page' => $variants->currentPage(),
                'last_page' => $variants->lastPage(),
                'total' => $variants->total(),
            ],
            'local_variants' => ProductVariant::query()->with('product:id,name')->whereIn('product_id', $productIds)->latest('id')->get(['id', 'product_id', 'type', 'value']),
            'suppliers' => DropshipSupplier::query()
                ->withCount(['variants', 'products'])
                ->latest('id')
                ->get(['id', 'name', 'key', 'is_active', 'last_connection_status']),
        ]);
    }

    public function pricingRules()
    {
        $suppliers = DropshipSupplier::query()->latest('id')->get(['id', 'name', 'key', 'driver_key', 'pricing_rules', 'price_field_mapping']);
        $profiles = DropshipDriverProfile::query()->get(['key', 'field_mapping']);

        return Inertia::render('Admin/Dropshipping/PricingRules', [
            'suppliers' => $suppliers->map(function (DropshipSupplier $supplier) use ($profiles): array {
                $profile = $profiles->firstWhere('key', $supplier->driver_key);
                $fields = is_array($profile?->field_mapping['fields'] ?? null) ? $profile->field_mapping['fields'] : [];
                $options = collect(array_values(array_filter($fields, 'is_string')))
                    ->merge([$supplier->price_field_mapping['cost_field'] ?? null, $supplier->price_field_mapping['maximum_field'] ?? null, 'sale_price', 'price', 'cost', 'max_price', 'dealer_price', 'mrp', 'wholesale_price', 'retail_price'])
                    ->filter(fn ($field): bool => is_string($field) && $field !== '')
                    ->unique()->values();
                $sample = $supplier->products()->latest('id')->first(['cost_price', 'max_price']);

                return [
                    'id' => $supplier->id, 'name' => $supplier->name, 'key' => $supplier->key,
                    'driver_key' => $supplier->driver_key, 'pricing_rules' => $supplier->pricing_rules ?? [],
                    'price_field_mapping' => $supplier->price_field_mapping ?? [],
                    'price_field_options' => $options,
                    'sample_cost' => $sample?->cost_price, 'sample_maximum' => $sample?->max_price,
                ];
            })->values(),
        ]);
    }

    public function updatePricing(Request $request, DropshipSupplier $supplier)
    {
        $data = $request->validate([
            'selling_base' => ['required', 'in:supplier_cost,supplier_maximum'],
            'selling_adjustment_type' => ['required', 'in:none,plus_fixed,minus_fixed,plus_percent,minus_percent'],
            'selling_adjustment_value' => ['required', 'numeric', 'min:0'],
            'minimum_base' => ['required', 'in:supplier_cost,supplier_maximum'],
            'minimum_adjustment_type' => ['required', 'in:none,plus_fixed,minus_fixed,plus_percent,minus_percent'],
            'minimum_adjustment_value' => ['required', 'numeric', 'min:0'],
            'minimum_cap_enabled' => ['nullable', 'boolean'],
            'maximum_base' => ['required', 'in:supplier_cost,supplier_maximum'],
            'maximum_adjustment_type' => ['required', 'in:none,plus_fixed,minus_fixed,plus_percent,minus_percent'],
            'maximum_adjustment_value' => ['required', 'numeric', 'min:0'],
            'maximum_cap_enabled' => ['nullable', 'boolean'],
            'regular_price_source' => ['required', 'in:capped_selling,raw_selling,minimum,maximum,supplier_cost,supplier_maximum'],
            'discount_type' => ['required', 'in:fixed,percent'],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'protect_minimum' => ['nullable', 'boolean'],
            'selling_rounding_mode' => ['required', 'in:none,up,down,nearest'],
            'selling_rounding_increment' => ['required', 'numeric', 'gt:0'],
            'minimum_rounding_mode' => ['required', 'in:none,up,down,nearest'],
            'minimum_rounding_increment' => ['required', 'numeric', 'gt:0'],
            'maximum_rounding_mode' => ['required', 'in:none,up,down,nearest'],
            'maximum_rounding_increment' => ['required', 'numeric', 'gt:0'],
            'discount_rounding_mode' => ['required', 'in:none,up,down,nearest'],
            'discount_rounding_increment' => ['required', 'numeric', 'gt:0'],
        ]);

        $supplier->forceFill([
            'pricing_rules' => [
                'selling' => ['base' => $data['selling_base'], 'adjustment_type' => $data['selling_adjustment_type'], 'adjustment_value' => (float) $data['selling_adjustment_value'], 'rounding' => ['mode' => $data['selling_rounding_mode'], 'increment' => (float) $data['selling_rounding_increment']]],
                'minimum' => ['base' => $data['minimum_base'], 'adjustment_type' => $data['minimum_adjustment_type'], 'adjustment_value' => (float) $data['minimum_adjustment_value'], 'cap_enabled' => (bool) ($data['minimum_cap_enabled'] ?? false), 'rounding' => ['mode' => $data['minimum_rounding_mode'], 'increment' => (float) $data['minimum_rounding_increment']]],
                'maximum' => ['base' => $data['maximum_base'], 'adjustment_type' => $data['maximum_adjustment_type'], 'adjustment_value' => (float) $data['maximum_adjustment_value'], 'cap_enabled' => (bool) ($data['maximum_cap_enabled'] ?? false), 'rounding' => ['mode' => $data['maximum_rounding_mode'], 'increment' => (float) $data['maximum_rounding_increment']]],
                'regular_price_source' => $data['regular_price_source'],
                'discount' => ['type' => $data['discount_type'], 'value' => (float) $data['discount_value'], 'protect_minimum' => (bool) ($data['protect_minimum'] ?? false)],
                'discount_rounding' => ['mode' => $data['discount_rounding_mode'], 'increment' => (float) $data['discount_rounding_increment']],
            ],
        ])->save();

        return back()->with('status', 'Pricing rules saved.');
    }

    public function updatePriceMapping(Request $request, DropshipSupplier $supplier)
    {
        $data = $request->validate([
            'cost_field' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_.\[\]-]+$/'],
            'maximum_field' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_.\[\]-]+$/'],
        ]);
        $supplier->forceFill(['price_field_mapping' => [
            'cost_field' => $data['cost_field'],
            'maximum_field' => $data['maximum_field'],
        ]])->save();

        return back()->with('status', 'Supplier price field mapping saved.');
    }

    public function previewPricing(Request $request, DropshipSupplier $supplier, PricingEngine $engine)
    {
        $data = $request->validate([
            'cost' => ['nullable', 'numeric', 'min:0'],
            'maximum' => ['nullable', 'numeric', 'min:0'],
            'rules' => ['required', 'array'],
        ]);

        return response()->json($engine->calculate($data['cost'] ?? null, $data['maximum'] ?? null, $data['rules'])->toArray());
    }

    public function mapCategory(Request $request, DropshipSupplierCategory $supplierCategory, CategoryMapper $mapper)
    {
        $data = $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'new_category_name' => ['nullable', 'string', 'max:120'],
        ]);

        if (empty($data['category_id']) && empty($data['new_category_name'])) {
            return back()->withErrors(['category_id' => 'Select a category or enter a new name.']);
        }

        if (!empty($data['new_category_name'])) {
            $category = Category::create([
                'name' => $data['new_category_name'],
                'slug' => Str::slug($data['new_category_name']),
                'is_active' => true,
            ]);
        } else {
            $category = Category::query()->findOrFail($data['category_id']);
        }

        $mapper->mapManually($supplierCategory->supplier, $supplierCategory->supplier_category_key, $category);

        return back()->with('status', 'Category mapping saved.');
    }

    public function uploadCategoryImage(Request $request, DropshipSupplierCategory $supplierCategory)
    {
        $request->validate([
            'image' => ['required', 'image', 'max:2048'],
        ]);

        if ($supplierCategory->image) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($supplierCategory->image);
        }

        $path = $request->file('image')->store('supplier_categories', 'public');
        $supplierCategory->forceFill(['image' => $path])->save();

        return back()->with('status', 'Supplier category image updated.');
    }

    public function importSupplierProduct(DropshipSupplierProduct $supplierProduct, ProductImportService $imports)
    {
        $result = $imports->import($supplierProduct);

        if ($result->status === ProductImportResult::BLOCKED) {
            return back()->withErrors(['product' => 'Product import blocked: ' . ($result->reason ?? 'review required')]);
        }

        return back()->with('status', $result->status === ProductImportResult::ALREADY_LINKED
            ? 'Product is already linked.'
            : 'Product imported as a draft.');
    }

    public function publishImportedProduct(Request $request, Product $product)
    {
        abort_unless($product->supplierLinks()->where('product_created_by_integration', true)->exists(), 404);

        $flags = $this->publishFlags($request, $product);
        $product->forceFill(['is_published' => true, ...$flags]);

        if ($request->has('regular_price')) {
            $product->regular_price = $request->input('regular_price');
        }
        if ($request->has('sale_price')) {
            $product->sale_price = $request->input('sale_price');
        }

        $product->save();

        return back()->with('status', 'Imported product published.');
    }

    public function bulkPublishImportedProducts(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer', 'distinct', 'exists:products,id'],
            'prices' => ['nullable', 'array'],
            'prices.*.regular_price' => ['nullable', 'numeric', 'min:0'],
            'prices.*.sale_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $products = Product::query()
            ->whereIn('id', $data['ids'])
            ->where('is_published', false)
            ->whereHas('supplierLinks', fn ($query) => $query->where('product_created_by_integration', true))
            ->get();

        $published = 0;
        foreach ($products as $product) {
            $product->forceFill(['is_published' => true, ...$this->publishFlags($request, $product)]);
            
            if (isset($data['prices'][$product->id])) {
                if (isset($data['prices'][$product->id]['regular_price'])) {
                    $product->regular_price = $data['prices'][$product->id]['regular_price'];
                }
                if (array_key_exists('sale_price', $data['prices'][$product->id])) {
                    $product->sale_price = $data['prices'][$product->id]['sale_price'];
                }
            }
            
            $product->save();
            ++$published;
        }

        return back()->with('status', "{$published} imported product(s) published.");
    }

    public function bulkUnpublishImportedProducts(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer', 'distinct', 'exists:products,id'],
        ]);

        $unpublished = Product::query()
            ->whereIn('id', $data['ids'])
            ->where('is_published', true)
            ->whereHas('supplierLinks', fn ($query) => $query->where('product_created_by_integration', true))
            ->update(['is_published' => false, 'updated_at' => now()]);

        return back()->with('status', "{$unpublished} imported product(s) unpublished.");
    }

    public function bulkSyncImportedProducts(Request $request, ProductImportService $importService, \App\Services\Dropshipping\PriceStockSyncService $priceStockService)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer', 'distinct', 'exists:products,id'],
        ]);

        $products = Product::query()
            ->whereIn('id', $data['ids'])
            ->with(['supplierLinks.supplierProduct'])
            ->get();

        $synced = 0;
        foreach ($products as $product) {
            $link = $product->supplierLinks->first();
            if ($link?->supplierProduct) {
                // syncProductContent handles updates via import
                $importService->import($link->supplierProduct);
                $priceStockService->sync($link->supplierProduct);
                ++$synced;
            }
        }

        return back()->with('status', "{$synced} imported product(s) successfully synchronized from supplier.");
    }

    private function publishFlags(Request $request, Product $product): array
    {
        $flags = [
            'is_featured' => $request->boolean('is_featured'),
            'is_new_arrival' => $request->boolean('is_new_arrival'),
            'is_best_seller' => $request->boolean('is_best_seller'),
            'is_flash_sale' => $request->boolean('is_flash_sale'),
        ];
        if ($flags['is_flash_sale'] && ! $product->is_flash_sale) {
            $flags['flash_sale_position'] = (int) Product::where('is_flash_sale', true)->max('flash_sale_position') + 1;
        } elseif (! $flags['is_flash_sale']) {
            $flags['flash_sale_position'] = 0;
        }

        return $flags;
    }

    public function mapVariation(Request $request, DropshipSupplierVariant $variant)
    {
        $data = $request->validate(['product_variant_id' => ['required', 'integer', 'exists:product_variants,id']]);
        $variant->load('supplierProduct.productLink');
        $localVariant = ProductVariant::query()->findOrFail($data['product_variant_id']);
        abort_unless($variant->supplierProduct?->productLink?->product_id === $localVariant->product_id, 422, 'Variant must belong to the linked local product.');

        $variant->variantLink()->updateOrCreate([], ['product_variant_id' => $localVariant->id]);

        return back()->with('status', 'Variation mapping saved.');
    }

    public function apiSettings(SupplierRegistry $registry)
    {
        return Inertia::render('Admin/Dropshipping/ApiSettings', [
            'suppliers' => DropshipSupplier::query()->latest('id')->get([
                'id', 'key', 'name', 'driver_key', 'base_url', 'sync_rules', 'is_active',
                'last_connection_status', 'last_connection_message', 'last_connection_tested_at',
            ]),
            'available_drivers' => $registry->driverKeys(),
            'driver_profiles' => DropshipDriverProfile::query()->latest('id')->get([
                'id', 'key', 'name', 'auth_key_header', 'auth_secret_header', 'products_path',
                'categories_path', 'collection_path', 'pagination_param', 'default_currency',
                'field_mapping', 'is_active',
            ]),
        ]);
    }

    public function storeDriverProfile(Request $request, SupplierRegistry $registry)
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9][a-z0-9_-]*$/', 'unique:dropship_driver_profiles,key'],
            'name' => ['required', 'string', 'max:120'],
            'auth_key_header' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9][A-Za-z0-9_-]*$/'],
            'auth_secret_header' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9][A-Za-z0-9_-]*$/'],
            'products_path' => ['required', 'string', 'max:255'],
            'categories_path' => ['nullable', 'string', 'max:255'],
            'collection_path' => ['nullable', 'string', 'max:255'],
            'pagination_param' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9_-]+$/'],
            'default_currency' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'field_mapping' => ['nullable', 'string', 'max:20000'],
        ]);
        if ($registry->hasDriver($data['key'])) {
            throw ValidationException::withMessages(['key' => 'This driver key is already registered. Choose a new key.']);
        }
        foreach (['products_path', 'categories_path', 'collection_path'] as $path) {
            if (($data[$path] ?? null) !== null && ! $this->isSafeRelativePath($data[$path])) {
                throw ValidationException::withMessages([$path => 'Use a relative API path without a leading slash, query string, or .. traversal.']);
            }
        }
        $mapping = $this->decodeFieldMapping($data['field_mapping'] ?? null);

        DropshipDriverProfile::create([
            'key' => $data['key'],
            'name' => $data['name'],
            'auth_key_header' => $data['auth_key_header'],
            'auth_secret_header' => $data['auth_secret_header'],
            'products_path' => $data['products_path'],
            'categories_path' => $data['categories_path'] ?? null,
            'collection_path' => $data['collection_path'] ?? null,
            'pagination_param' => $data['pagination_param'],
            'default_currency' => strtoupper($data['default_currency']),
            'field_mapping' => $mapping,
            'is_active' => true,
        ]);

        return back()->with('status', 'REST driver profile saved. It is now available as a supplier driver.');
    }

    public function toggleDriverProfile(DropshipDriverProfile $profile)
    {
        $profile->forceFill(['is_active' => ! $profile->is_active])->save();

        return back()->with('status', $profile->is_active ? 'REST driver profile enabled.' : 'REST driver profile disabled.');
    }

    public function updateDriverProfile(Request $request, DropshipDriverProfile $profile)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'auth_key_header' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9][A-Za-z0-9_-]*$/'],
            'auth_secret_header' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9][A-Za-z0-9_-]*$/'],
            'products_path' => ['required', 'string', 'max:255'],
            'categories_path' => ['nullable', 'string', 'max:255'],
            'collection_path' => ['nullable', 'string', 'max:255'],
            'pagination_param' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9_-]+$/'],
            'default_currency' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'field_mapping' => ['nullable', 'string', 'max:20000'],
        ]);
        foreach (['products_path', 'categories_path', 'collection_path'] as $path) {
            if (($data[$path] ?? null) !== null && $data[$path] !== '' && ! $this->isSafeRelativePath($data[$path])) {
                throw ValidationException::withMessages([$path => 'Use a relative API path without a leading slash, query string, or .. traversal.']);
            }
        }

        $profile->forceFill([
            'name' => $data['name'],
            'auth_key_header' => $data['auth_key_header'],
            'auth_secret_header' => $data['auth_secret_header'],
            'products_path' => $data['products_path'],
            'categories_path' => $data['categories_path'] ?? null,
            'collection_path' => $data['collection_path'] ?? null,
            'pagination_param' => $data['pagination_param'],
            'default_currency' => strtoupper($data['default_currency']),
            'field_mapping' => $this->decodeFieldMapping($data['field_mapping'] ?? null),
        ])->save();

        return back()->with('status', 'Supplier API profile updated.');
    }

    private function decodeFieldMapping(?string $json): ?array
    {
        if (trim((string) $json) === '') return null;
        $mapping = json_decode($json, true);
        if (! is_array($mapping)) {
            throw ValidationException::withMessages(['field_mapping' => 'Field mapping must be valid JSON.']);
        }
        return $mapping;
    }

    private function isSafeRelativePath(?string $path): bool
    {
        return is_string($path) && $path !== '' && ! str_starts_with($path, '/') && ! str_contains($path, '..') && ! preg_match('/[?#]/', $path);
    }

    private function uniqueSupplierKey(?string $requested, string $name, string $driverKey): string
    {
        $base = Str::slug(trim((string) ($requested ?: $name ?: $driverKey)), '-');
        $base = substr($base !== '' ? $base : 'supplier', 0, 70);
        $candidate = $base;
        $suffix = 2;
        while (DropshipSupplier::withTrashed()->where('key', $candidate)->exists()) {
            $candidate = substr($base, 0, 70 - strlen((string) $suffix) - 1) . '-' . $suffix++;
        }

        return $candidate;
    }

    public function store(Request $request, SupplierRegistry $registry)
    {
        $data = $request->validate([
            'key' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9][a-z0-9_-]*$/', 'unique:dropship_suppliers,key'],
            'name' => ['required', 'string', 'max:120'],
            'driver_key' => ['required', 'string', 'max:80'],
            'base_url' => ['required', 'url:https', 'max:255'],
            'category_endpoint' => ['nullable', 'string', 'max:255'],
            'api_key' => ['nullable', 'string', 'max:1000'],
            'secret_key' => ['nullable', 'string', 'max:1000'],
            'import_status' => ['nullable', 'in:draft,publish,pending,private'],
            'import_images' => ['nullable', 'boolean'],
            'import_variations' => ['nullable', 'boolean'],
        ]);
        if (! $registry->hasDriver($data['driver_key'])) {
            throw ValidationException::withMessages(['driver_key' => 'This supplier driver is not registered by the application.']);
        }
        $data['key'] = $this->uniqueSupplierKey($data['key'] ?? null, $data['name'], $data['driver_key']);

        DropshipSupplier::create([
            'key' => $data['key'],
            'name' => $data['name'],
            'driver_key' => $data['driver_key'],
            'base_url' => $data['base_url'],
            'api_key' => $data['api_key'] ?? null,
            'secret_key' => $data['secret_key'] ?? null,
            'sync_rules' => [
                'category_endpoint' => $data['category_endpoint'] ?? null,
                'import_status' => $data['import_status'] ?? 'draft',
                'import_images' => (bool) ($data['import_images'] ?? false),
                'import_variations' => (bool) ($data['import_variations'] ?? false),
            ],
            'is_active' => false,
        ]);

        return back()->with('status', 'Supplier saved.');
    }

    public function update(Request $request, DropshipSupplier $supplier, SupplierRegistry $registry)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'driver_key' => ['required', 'string', 'max:80'],
            'base_url' => ['required', 'url:https', 'max:255'],
            'category_endpoint' => ['nullable', 'string', 'max:255'],
            'api_key' => ['nullable', 'string', 'max:1000'],
            'secret_key' => ['nullable', 'string', 'max:1000'],
            'import_status' => ['nullable', 'in:draft,publish,pending,private'],
            'import_images' => ['nullable', 'boolean'],
            'import_variations' => ['nullable', 'boolean'],
        ]);
        if (! $registry->hasDriver($data['driver_key'])) {
            throw ValidationException::withMessages(['driver_key' => 'This supplier driver is not registered by the application.']);
        }

        foreach (['api_key', 'secret_key'] as $credential) {
            if (trim((string) ($data[$credential] ?? '')) === '') {
                unset($data[$credential]);
            }
        }
        $syncRules = is_array($supplier->sync_rules) ? $supplier->sync_rules : [];
        $syncRules['category_endpoint'] = $data['category_endpoint'] ?? null;
        $syncRules['import_status'] = $data['import_status'] ?? ($syncRules['import_status'] ?? 'draft');
        $syncRules['import_images'] = (bool) ($data['import_images'] ?? ($syncRules['import_images'] ?? false));
        $syncRules['import_variations'] = (bool) ($data['import_variations'] ?? ($syncRules['import_variations'] ?? false));

        $supplier->forceFill([
            'name' => $data['name'],
            'driver_key' => $data['driver_key'],
            'base_url' => $data['base_url'],
            'sync_rules' => $syncRules,
            ...array_intersect_key($data, array_flip(['api_key', 'secret_key'])),
        ])->save();

        return back()->with('status', 'Supplier updated.');
    }

    public function toggle(DropshipSupplier $supplier)
    {
        $supplier->forceFill(['is_active' => ! $supplier->is_active])->save();

        return back()->with('status', $supplier->is_active ? 'Supplier activated.' : 'Supplier disabled.');
    }

    public function testConnection(DropshipSupplier $supplier)
    {
        // A connection check is a short interactive action. Run this one
        // synchronously so the admin sees success/failure on the next page
        // load; catalog and price/stock syncs remain queued operations.
        try {
            TestSupplierConnection::dispatchSync($supplier->id);
        } catch (Throwable) {
            $supplier->forceFill([
                'last_connection_status' => 'failed',
                'last_connection_message' => 'Supplier connection check could not be completed.',
                'last_connection_tested_at' => now(),
            ])->save();
        }

        $supplier->refresh();
        $message = $supplier->last_connection_message ?: ($supplier->last_connection_status === 'success'
            ? 'Connection succeeded.'
            : 'Supplier connection failed.');

        return back()->with('status', $message);
    }

    public function catalog(DropshipSupplier $supplier, SyncRunService $syncRuns)
    {
        $existing = $this->unfinishedCatalogRun($supplier);
        if ($existing !== null) {
            return $this->resumeCatalogRun($existing);
        }

        $run = $syncRuns->createRun($supplier, 'catalog', request()->user());
        StartSupplierCatalogSync::dispatch($run->id);

        return back();
    }

    public function priceStock(DropshipSupplier $supplier, SyncRunService $syncRuns)
    {
        $run = $syncRuns->createRun($supplier, 'price_stock', request()->user());
        StartSupplierPriceStockSync::dispatch($run->id);

        return back();
    }

    public function cancelRun(DropshipSyncRun $run, SyncRunService $syncRuns)
    {
        abort_unless($syncRuns->cancel($run), 409, 'This sync run is no longer cancellable.');

        return back()->with('status', 'Sync run cancellation requested.');
    }

    public function pauseCatalogRun(DropshipSyncRun $run)
    {
        abort_unless($run->type === 'catalog', 404);
        abort_unless(in_array($run->status, [SyncRunState::QUEUED, SyncRunState::RUNNING], true), 409, 'Only active catalog sync runs can be stopped.');

        DropshipSyncRun::query()
            ->where('supplier_id', $run->supplier_id)
            ->where('type', 'catalog')
            ->whereIn('status', [SyncRunState::QUEUED, SyncRunState::RUNNING])
            ->update(['status' => SyncRunState::PAUSED, 'updated_at' => now()]);

        return back()->with('status', 'Catalog sync stopped. You can resume unfinished pages.');
    }

    public function resumeCatalogRun(DropshipSyncRun $run)
    {
        abort_unless($run->type === 'catalog', 404);
        abort_unless(in_array($run->status, [SyncRunState::QUEUED, SyncRunState::RUNNING, SyncRunState::PAUSED], true), 409, 'Only unfinished catalog sync runs can be resumed.');

        if ($run->status === SyncRunState::PAUSED) {
            $run->forceFill(['status' => SyncRunState::RUNNING])->save();
        }

        $unfinishedItems = $run->items()
            ->whereIn('status', [SyncRunState::ITEM_QUEUED, SyncRunState::ITEM_RUNNING])
            ->get(['id', 'item_key', 'status']);

        if ($unfinishedItems->isEmpty()) {
            StartSupplierCatalogSync::dispatch($run->id);

            return back()->with('status', 'Catalog sync resume queued.');
        }

        foreach ($unfinishedItems as $item) {
            if ($item->status === SyncRunState::ITEM_RUNNING) {
                $item->forceFill([
                    'status' => SyncRunState::ITEM_QUEUED,
                    'started_at' => null,
                ])->save();
            }

            if (preg_match('/^page:(\d+)$/', $item->item_key, $matches)) {
                SyncSupplierCatalogPage::dispatch($run->id, (int) $matches[1]);
            }
        }

        DropshipSyncRun::query()
            ->where('supplier_id', $run->supplier_id)
            ->where('type', 'catalog')
            ->where('id', '!=', $run->id)
            ->whereIn('status', [SyncRunState::QUEUED, SyncRunState::RUNNING])
            ->update(['status' => SyncRunState::PAUSED, 'updated_at' => now()]);

        return back()->with('status', 'Unfinished catalog sync pages were queued again.');
    }

    public function workCatalogQueue(DropshipSupplier $supplier)
    {
        $run = $this->unfinishedCatalogRun($supplier);
        abort_unless($run !== null, 409, 'No unfinished catalog sync run exists.');
        abort_unless($run->status !== SyncRunState::PAUSED, 409, 'Catalog sync is stopped.');

        $item = $run->items()
            ->whereIn('status', [SyncRunState::ITEM_QUEUED, SyncRunState::ITEM_RUNNING])
            ->orderBy('id')
            ->first(['id', 'item_key', 'status']);

        if ($item === null) {
            StartSupplierCatalogSync::dispatchSync($run->id);
        } elseif (preg_match('/^page:(\d+)$/', $item->item_key, $matches)) {
            if ($item->status === SyncRunState::ITEM_RUNNING) {
                $item->forceFill([
                    'status' => SyncRunState::ITEM_QUEUED,
                    'started_at' => null,
                ])->save();
            }

            SyncSupplierCatalogPage::dispatchSync($run->id, (int) $matches[1]);
        }

        return response()->json([
            'status' => 'processed',
            'progress' => $this->catalogProgressData($supplier),
            'run' => $this->unfinishedCatalogRun($supplier),
        ]);
    }

    public function activeRun(DropshipSupplier $supplier)
    {
        $run = $this->unfinishedCatalogRun($supplier);
        return response()->json($run);
    }

    public function catalogProgress(DropshipSupplier $supplier)
    {
        return response()->json($this->catalogProgressData($supplier));
    }

    private function unfinishedCatalogRun(DropshipSupplier $supplier): ?DropshipSyncRun
    {
        return $supplier->syncRuns()
            ->where('type', 'catalog')
            ->whereIn('status', [SyncRunState::QUEUED, SyncRunState::RUNNING, SyncRunState::PAUSED])
            ->orderByDesc('processed_items')
            ->orderByRaw("case when status = 'running' then 0 when status = 'paused' then 1 else 2 end")
            ->orderByDesc('id')
            ->first();
    }

    private function catalogProgressData(DropshipSupplier $supplier): array
    {
        $run = $this->unfinishedCatalogRun($supplier);
        if ($run === null) {
            return [
                'run' => null,
                'items' => [],
                'queue_jobs' => DB::table('jobs')->count(),
                'mirrored_products' => $supplier->products()->count(),
                'mirrored_variants' => $supplier->variants()->count(),
            ];
        }

        return [
            'run' => $run,
            'items' => $run->items()
                ->orderBy('id')
                ->get(['item_key', 'status', 'error_summary', 'started_at', 'finished_at', 'updated_at']),
            'queue_jobs' => DB::table('jobs')->count(),
            'mirrored_products' => $supplier->products()->count(),
            'mirrored_variants' => $supplier->variants()->count(),
        ];
    }
}
