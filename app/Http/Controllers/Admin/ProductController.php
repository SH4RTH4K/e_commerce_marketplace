<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexProductRequest;
use App\Http\Requests\SaveProductRequest;
use App\Models\DropshipProductLink;
use App\Models\DropshipSupplier;
use App\Models\DropshipSupplierProduct;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\SupplierAudit;
use App\Services\CategoryHierarchyService;
use App\Services\InventoryStockService;
use App\Services\ProductCatalogService;
use App\Services\ProductPricingService;
use App\Services\SkuConfigurationService;
use App\Support\PublicUploader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(IndexProductRequest $request, ProductCatalogService $catalog): Response
    {
        $filters = $catalog->filters($request);
        $query = $catalog->query($filters);
        $totals = (clone $query)->selectRaw('COUNT(*) AS total, SUM(CASE WHEN is_published = 1 THEN 1 ELSE 0 END) AS published, SUM(CASE WHEN is_published = 0 THEN 1 ELSE 0 END) AS draft')->first();
        $catalog->visibility($query, $filters['status']);
        $catalog->sort($query, $filters);

        return Inertia::render('Admin/Products/Index', [
            'products' => $query->with('category', 'images', 'supplier:id,name,supplier_code', 'supplierLinks.supplierProduct.supplier')->withCount('variants')
                ->paginate($filters['per_page'])->appends($catalog->parameters($filters))->through(fn ($product) => [
                    'id' => $product->id, 'name' => $product->name, 'sku' => $product->sku, 'brand' => $product->brand,
                    'url_key' => $product->url_key, 'is_published' => $product->is_published,
                    'price' => $product->sale_price ?? $product->regular_price, 'regular_price' => $product->regular_price, 'sale_price' => $product->sale_price,
                    ...app(ProductPricingService::class)->inventoryDetails($product),
                    'stock_quantity' => (int) $product->stock_quantity,
                    'supplier_stock_quantity' => $product->source_type === 'dropshipping' || $product->supplierLinks->contains('product_created_by_integration', true) ? (int) $product->stock_quantity : null,
                    'local_stock_quantity' => ($product->source_type === 'dropshipping' || $product->supplierLinks->contains('product_created_by_integration', true) ? 0 : (int) $product->stock_quantity) + (int) $product->sourced_stock_quantity,
                    ...$product->only(['allow_backorder', 'sourced_stock_quantity', 'available_stock_quantity', 'can_order', 'availability_label', 'hide_stock_quantity']),
                    'has_variants' => $product->variants_count > 0,
                    'variants_count' => (int) $product->variants_count, 'image_url' => $product->imageUrl(),
                    'category' => $product->category ? ['name' => $product->category->name] : null,
                    'supplier' => $product->supplier?->name ?: $product->supplierLinks->first()?->supplierProduct?->supplier?->name,
                    'supplier_code' => $product->supplierLinks->first()?->supplierProduct?->product_code ?: $product->supplierLinks->first()?->supplierProduct?->supplier_product_id,
                    'supplier_product_id' => $product->supplierLinks->first()?->supplierProduct?->supplier_product_id,
                    'supplier_account_code' => $product->supplier?->supplier_code ?: $product->supplierLinks->first()?->supplierProduct?->supplier?->supplier_code,
                    'source_type' => $product->source_type ?: ($product->supplierLinks->isNotEmpty() ? 'dropshipping' : 'manual'),
                    'updated_at' => $product->updated_at?->setTimezone('Asia/Dhaka')->format('d M Y, h:i A'),
                ]),
            'categories' => app(CategoryHierarchyService::class)->options(),
            'suppliers' => DropshipSupplier::orderBy('name')->get(['id', 'name']),
            'filters' => $filters, 'q' => $filters['q'], 'category' => $filters['category'], 'status' => $filters['status'],
            'summary' => ['all' => (int) $totals->total, 'published' => (int) $totals->published, 'draft' => (int) $totals->draft],
            'canEdit' => $request->user()->hasPermission('products.create'), 'canDelete' => $request->user()->hasPermission('products.delete'),
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('Admin/Products/Form', [
            'product' => ['is_published' => true, 'regular_price' => 0, 'stock_quantity' => 0, 'allow_backorder' => false, 'sourced_stock_quantity' => 0, 'specifications' => [], 'variants' => [], 'images' => []],
            'categories' => app(CategoryHierarchyService::class)->options(),
            'suppliers' => $this->supplierOptions(),
            'canManageSuppliers' => $request->user()->hasPermission('suppliers.manage'),
            'canViewSuppliers' => $request->user()->hasPermission('suppliers.view'),
            'returnTo' => ProductCatalogService::returnUrl($request->input('return_to')),
        ]);
    }

    public function store(SaveProductRequest $request)
    {
        $data = $request->validated();
        $this->validateSupplierAssignment($data['supplier_id'] ?? null);
        if (($data['source_type'] ?? 'manual') === 'dropshipping') {
            throw ValidationException::withMessages(['source_type' => 'Import or link this product through Dropshipping to assign its integration source.']);
        }
        $data['source_type'] ??= 'manual';
        $data = $this->sanitizeProductData($data, $request);
        $data['slug'] = ($data['slug'] ?? '') ?: $data['name'];
        $this->applyFlags($request, $data, null);
        $data['specifications'] = $this->normalizeSpecifications($request);

        $data = app(ProductPricingService::class)->prepare($data);
        $product = DB::transaction(function () use ($data, $request) {
            $this->validateSupplierAssignment($data['supplier_id'] ?? null, true);
            $product = Product::create($data);
            app(SkuConfigurationService::class)->assignNew($product);
            $this->syncVariants($product, $request);
            $this->storeImages($product, $request);
            app(InventoryStockService::class)->recordOpening($request->user(), $product);

            return $product;
        });

        return redirect()->route('admin.products.edit', ['product' => $product->id, ...($request->filled('return_to') ? ['return_to' => ProductCatalogService::returnUrl($request->input('return_to'))] : [])])->with('status', 'Product created.');
    }

    public function edit(Request $request, Product $product)
    {
        $product->load('images', 'variants', 'supplier:id,name,key,supplier_code,supplier_type,is_active', 'supplierLinks.supplierProduct.supplier:id,name,key');
        $assignment = $this->assignmentContext($product);

        // Group variants
        $variants = $product->variants->map(fn ($v) => [
            'id' => $v->id,
            'type' => $v->type,
            'value' => $v->value,
            'color_code' => $v->color_code,
            'price_delta' => $v->price_delta,
            'stock' => $v->stock,
            'image_path' => $v->image_path,
        ])->values();

        // Custom variant groups (any type other than Size, Color, Weight)
        $customGroups = $product->variants
            ->whereNotIn('type', ['Size', 'Color', 'Weight'])
            ->groupBy('type')
            ->map(fn ($items, $type) => [
                'name' => $type,
                'options' => $items->map(fn ($v) => [
                    'id' => $v->id,
                    'value' => $v->value,
                    'price_delta' => $v->price_delta,
                    'stock' => $v->stock,
                    'image_path' => $v->image_path,
                ])->values(),
            ])
            ->values();

        return Inertia::render('Admin/Products/Form', [
            'product' => array_merge($product->toArray(), [
                'supplier_id' => $product->supplier_id ?? $assignment['supplier_id'],
                'source_type' => $assignment['locked'] ? 'dropshipping' : ($product->source_type ?: 'manual'),
                ...$product->only(['purchase_price', 'selling_price', 'minimum_price', 'maximum_price', 'pricing_mode', 'enforce_minimum_price', 'enforce_maximum_price', 'protect_minimum_price']),
                'images' => $product->images->map(fn ($img) => ['id' => $img->id, 'path' => $img->path, 'alt' => $img->alt, 'is_primary' => $img->is_primary])->values(),
                'variants' => $variants,
                'custom_variant_groups' => $customGroups,
                'specifications' => $product->specifications ?? [],
            ]),
            'categories' => app(CategoryHierarchyService::class)->options(),
            'suppliers' => $this->supplierOptions($product->supplier_id),
            'assignment' => $assignment,
            'canManageSuppliers' => $request->user()->hasPermission('suppliers.manage'),
            'canViewSuppliers' => $request->user()->hasPermission('suppliers.view'),
            'pricing' => app(ProductPricingService::class)->context($product),
            'returnTo' => ProductCatalogService::returnUrl($request->input('return_to')),
        ]);
    }

    public function update(SaveProductRequest $request, Product $product)
    {
        $data = $request->validated();
        if (array_key_exists('source_type', $data) && $data['source_type'] === null) {
            $data['source_type'] = $product->source_type ?: 'manual';
        }
        if (array_key_exists('supplier_id', $data) && (int) ($data['supplier_id'] ?? 0) !== (int) ($product->supplier_id ?? 0)) {
            $this->validateSupplierAssignment($data['supplier_id'] ?? null);
        }
        $data = $this->sanitizeProductData($data, $request, $product);
        $data['slug'] = ($data['slug'] ?? '') ?: $product->slug;
        $this->applyFlags($request, $data, $product);
        $data['specifications'] = $this->normalizeSpecifications($request);

        DB::transaction(function () use ($product, $data, $request) {
            $links = $product->supplierLinks()->orderBy('id')->get(['id', 'supplier_product_row_id']);
            // Match the supplier sync lock order so an edit and sync cannot
            // overwrite each other or deadlock over product/link rows.
            DropshipSupplierProduct::whereIn('id', $links->pluck('supplier_product_row_id'))->orderBy('id')->lockForUpdate()->get();
            DropshipProductLink::whereIn('id', $links->pluck('id'))->orderBy('id')->lockForUpdate()->get();
            $product = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            $assignment = $this->assignmentContext($product);
            if ($assignment['conflict']) {
                throw ValidationException::withMessages(['supplier_id' => 'Conflicting supplier ownership requires review in Dropshipping before saving.']);
            }
            if ($assignment['locked']) {
                if ((array_key_exists('supplier_id', $data) && (int) ($data['supplier_id'] ?? 0) !== (int) $assignment['supplier_id'])
                    || (isset($data['source_type']) && $data['source_type'] !== 'dropshipping')) {
                    throw ValidationException::withMessages(['supplier_id' => 'This product belongs to an integration. Use the validated Dropshipping relink workflow to change its supplier.']);
                }
                $data['supplier_id'] = $assignment['supplier_id'];
                $data['source_type'] = 'dropshipping';
            } elseif (($data['source_type'] ?? $product->source_type) === 'dropshipping') {
                throw ValidationException::withMessages(['source_type' => 'A Dropshipping source requires an integration product link.']);
            }
            $supplierChanged = array_key_exists('supplier_id', $data) && (int) ($data['supplier_id'] ?? 0) !== (int) ($product->supplier_id ?? 0);
            if ($supplierChanged) {
                $this->validateSupplierAssignment($data['supplier_id'], true);
            }
            $beforeSupplier = $product->supplier_id;
            $beforeStock = (int) $product->stock_quantity;
            if (array_key_exists('sku', $data) && $data['sku'] !== $product->sku) {
                // Explicit admin edits also opt out of supplier SKU replacement.
                $product->forceFill(['sku_managed' => true]);
            }
            $data = app(ProductPricingService::class)->prepare($data, $product);
            $product->update($data);
            app(InventoryStockService::class)->recordProductEdit($request->user(), $product, $beforeStock);
            if ($supplierChanged) {
                SupplierAudit::create(['supplier_id' => $product->supplier_id ?? $beforeSupplier, 'actor_id' => $request->user()->id, 'actor_name' => $request->user()->name,
                    'event' => 'product_supplier_changed', 'before' => ['supplier_id' => $beforeSupplier], 'after' => ['supplier_id' => $product->supplier_id],
                    'metadata' => ['product_id' => $product->id], 'created_at' => now()]);
            }
            if (($data['pricing_mode'] ?? null) === 'auto') {
                $product->supplierLinks()->update(['price_override' => null]);
            }
        });
        $product->refresh();
        $this->syncVariants($product, $request);
        $this->storeImages($product, $request);

        return redirect()->route('admin.products.edit', ['product' => $product->id, ...($request->filled('return_to') ? ['return_to' => ProductCatalogService::returnUrl($request->input('return_to'))] : [])])->with('status', 'Product updated.');
    }

    public function destroy(Request $request, Product $product)
    {
        $product->delete();

        return redirect(ProductCatalogService::returnUrl($request->input('return_to')))->with('status', 'Product deleted.');
    }

    public function toggle(Request $request, Product $product)
    {
        $data = $request->validate(['is_published' => ['sometimes', 'required', 'boolean']]);
        $product->update(['is_published' => $data['is_published'] ?? ! $product->is_published]);

        return back()->with(
            'status',
            $product->is_published
                ? 'Product is now Active and visible on the storefront.'
                : 'Product is now Inactive and hidden from the storefront.'
        );
    }

    public function bulk(Request $request)
    {
        abort_unless($request->user()->hasPermission('products.create') || $request->user()->hasPermission('products.delete'), 403);
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer', 'distinct', 'exists:products,id'],
            'bulk_action' => ['required', 'in:activate,deactivate,delete,move_category'],
            'category_id' => ['required_if:bulk_action,move_category', 'nullable', 'integer', 'exists:categories,id'],
        ]);

        abort_unless($request->user()->hasPermission($data['bulk_action'] === 'delete' ? 'products.delete' : 'products.create'), 403);
        $ids = $data['ids'];
        $count = count($ids);

        if ($data['bulk_action'] === 'activate') {
            Product::whereIn('id', $ids)->update(['is_published' => true]);
            Cache::forget('sitemap_urls_v2');

            return back()->with('status', "{$count} product(s) set to Active.");
        }

        if ($data['bulk_action'] === 'deactivate') {
            Product::whereIn('id', $ids)->update(['is_published' => false]);
            Cache::forget('sitemap_urls_v2');

            return back()->with('status', "{$count} product(s) set to Inactive.");
        }

        if ($data['bulk_action'] === 'move_category') {
            Product::whereIn('id', $ids)->update(['category_id' => $data['category_id']]);

            return back()->with('status', "{$count} product(s) moved to the selected category.");
        }

        Product::whereIn('id', $ids)->delete();
        Cache::forget('sitemap_urls_v2');

        return back()->with('status', "{$count} product(s) deleted.");
    }

    public function destroyImage(Product $product, ProductImage $image)
    {
        abort_unless($image->product_id === $product->id, 404);
        $wasPrimary = $image->is_primary;
        $this->deletePublicUpload($image->path);
        $image->delete();

        if ($wasPrimary) {
            $product->images()->orderBy('position')->first()?->update(['is_primary' => true]);
        }

        return back()->with('status', 'Image removed.');
    }

    /* ------------------------------------------------------------------ */
    private function sanitizeProductData(array $data, ?Request $request = null, ?Product $product = null): array
    {
        $isVariable = ($data['product_type'] ?? '') === 'variable';

        unset(
            $data['product_type'],
            $data['spec_labels'],
            $data['spec_values'],
            $data['size_variants'],
            $data['color_variants'],
            $data['weight_variants'],
            $data['custom_variant_groups'],
            $data['images'],
            $data['images_b64'],
            $data['images_name']
        );

        $data['sale_price'] = array_key_exists('sale_price', $data)
            ? (($data['sale_price'] !== null && trim((string) $data['sale_price']) !== '') ? (float) $data['sale_price'] : null)
            : $product?->sale_price;

        $regularPrice = (float) ($data['regular_price'] ?? $product?->regular_price ?? 0);
        $stockQuantity = max(0, (int) ($data['stock_quantity'] ?? $product?->stock_quantity ?? 0));

        // For variable products: calculate total variant stock if not explicitly set
        if ($isVariable && $request) {
            $totalVarStock = 0;
            $hasVarStock = false;
            foreach (['color_variants', 'size_variants', 'weight_variants'] as $k) {
                foreach ($request->input($k, []) as $r) {
                    if (! empty($r['value'])) {
                        $totalVarStock += max(0, (int) ($r['stock'] ?? 0));
                        $hasVarStock = true;
                    }
                }
            }
            foreach ($request->input('custom_variant_groups', []) as $g) {
                foreach ($g['options'] ?? [] as $opt) {
                    if (! empty($opt['value'])) {
                        $totalVarStock += max(0, (int) ($opt['stock'] ?? 0));
                        $hasVarStock = true;
                    }
                }
            }
            if ($hasVarStock && $stockQuantity === 0) {
                $stockQuantity = $totalVarStock;
            }
        }

        $data['regular_price'] = $regularPrice;
        $data['stock_quantity'] = $stockQuantity;

        foreach (['sku', 'brand', 'unit', 'short_description', 'description', 'meta_title', 'meta_description', 'meta_keywords'] as $field) {
            if (isset($data[$field])) {
                $trimmed = trim((string) $data[$field]);
                $data[$field] = $trimmed !== '' ? $trimmed : null;
            }
        }

        return $data;
    }

    private function supplierOptions(?int $currentSupplierId = null): array
    {
        return DropshipSupplier::withTrashed()->where(fn ($query) => $query->whereNull('deleted_at')->when($currentSupplierId, fn ($query) => $query->orWhere('id', $currentSupplierId)))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get(['id', 'name', 'key', 'supplier_code', 'supplier_type', 'is_active', 'deleted_at'])
            ->map(fn (DropshipSupplier $supplier): array => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'key' => $supplier->key,
                'supplier_code' => $supplier->supplier_code ?: 'SUP-'.str_pad((string) $supplier->id, 5, '0', STR_PAD_LEFT),
                'supplier_type' => $supplier->supplier_type,
                'is_active' => $supplier->is_active && ! $supplier->trashed(),
            ])->values()->all();
    }

    private function validateSupplierAssignment(?int $supplierId, bool $lock = false): void
    {
        if ($supplierId === null) {
            return;
        }
        $supplier = DropshipSupplier::query()->when($lock, fn ($query) => $query->lockForUpdate())->find($supplierId);
        if (! $supplier || ! $supplier->is_active) {
            throw ValidationException::withMessages(['supplier_id' => 'Select an active supplier.']);
        }
    }

    private function assignmentContext(Product $product): array
    {
        $product->loadMissing('supplierLinks.supplierProduct.supplier:id,name,key');
        $links = $product->supplierLinks->filter(fn ($link) => $link->supplierProduct !== null && $link->product_created_by_integration);
        $supplierIds = $links->pluck('supplierProduct.supplier_id')->unique()->values();
        $conflict = $links->count() > 1 || ($supplierIds->isNotEmpty() && $product->supplier_id !== null && (int) $product->supplier_id !== (int) $supplierIds->first());
        $source = $links->first()?->supplierProduct;

        return ['locked' => $links->isNotEmpty(), 'supplier_id' => $supplierIds->first(), 'conflict' => $conflict,
            'integration' => $source?->supplier?->name, 'external_product_id' => $source?->supplier_product_id,
            'stock_source' => $links->isNotEmpty() ? 'Supplier-managed availability' : 'Local inventory'];
    }

    private function normalizeSpecifications(Request $request): array
    {
        $labels = $request->input('spec_labels', []);
        $values = $request->input('spec_values', []);
        if (! is_array($labels) || ! is_array($values)) {
            return [];
        }

        $rows = [];
        foreach ($labels as $i => $label) {
            $label = trim((string) $label);
            $value = trim((string) ($values[$i] ?? ''));
            if ($label === '' && $value === '') {
                continue;
            }
            $rows[] = [
                'label' => $label,
                'value' => $value,
            ];
        }

        return $rows;
    }

    private function applyFlags(Request $request, array &$data, ?Product $product = null): void
    {
        $data['is_published'] = $request->boolean('is_published');
        $data['is_featured'] = $request->boolean('is_featured');
        $data['is_new_arrival'] = $request->boolean('is_new_arrival');
        $data['is_best_seller'] = $request->boolean('is_best_seller');
        $data['is_flash_sale'] = $request->boolean('is_flash_sale');
        $data['is_free_shipping'] = $request->boolean('is_free_shipping');

        if ($data['is_flash_sale']) {
            $wasFlash = $product?->is_flash_sale ?? false;
            if (! $wasFlash) {
                $data['flash_sale_position'] = (int) Product::where('is_flash_sale', true)->max('flash_sale_position') + 1;
            }
        } else {
            $data['flash_sale_position'] = 0;
        }
    }

    private function syncVariants(Product $product, Request $request): void
    {
        // Always replace from the structured variant editor (empty = clear all options).
        $product->variants()->delete();

        // If simple product selected, do not create variants
        if ($request->input('product_type') === 'simple') {
            return;
        }

        $pos = 0;

        // 1. Color Variants
        $colors = collect($request->input('color_variants', []));
        foreach ($colors as $i => $row) {
            $val = trim((string) ($row['value'] ?? ''));
            if ($val === '') {
                continue;
            }

            $imagePath = ! empty($row['image_path']) ? trim((string) $row['image_path']) : null;
            if (! empty($row['image_b64'])) {
                $name = (string) ($row['image_name'] ?? ('color_'.$i.'.jpg'));
                $decoded = PublicUploader::decodeBase64Payload((string) $row['image_b64'], $name, 'variant_image');
                if ($decoded) {
                    $imagePath = PublicUploader::storeBytes($decoded[0], 'products/variants', $decoded[1], 'variant_image');
                }
            }

            $colorCode = ! empty($row['color_code']) ? trim((string) $row['color_code']) : null;

            ProductVariant::create([
                'product_id' => $product->id,
                'type' => 'Color',
                'value' => $val,
                'color_code' => $colorCode,
                'price_delta' => (float) ($row['price_delta'] ?? 0),
                'stock' => max(0, (int) ($row['stock'] ?? 0)),
                'image_path' => $imagePath,
                'position' => $pos++,
            ]);
        }

        // 2. Size Variants
        $sizes = collect($request->input('size_variants', []));
        foreach ($sizes as $i => $row) {
            $val = trim((string) ($row['value'] ?? ''));
            if ($val === '') {
                continue;
            }

            $imagePath = ! empty($row['image_path']) ? trim((string) $row['image_path']) : null;
            if (! empty($row['image_b64'])) {
                $name = (string) ($row['image_name'] ?? ('size_'.$i.'.jpg'));
                $decoded = PublicUploader::decodeBase64Payload((string) $row['image_b64'], $name, 'variant_image');
                if ($decoded) {
                    $imagePath = PublicUploader::storeBytes($decoded[0], 'products/variants', $decoded[1], 'variant_image');
                }
            }

            ProductVariant::create([
                'product_id' => $product->id,
                'type' => 'Size',
                'value' => $val,
                'color_code' => null,
                'price_delta' => (float) ($row['price_delta'] ?? 0),
                'stock' => max(0, (int) ($row['stock'] ?? 0)),
                'image_path' => $imagePath,
                'position' => $pos++,
            ]);
        }

        // 3. Weight / Legacy Variants
        $weights = collect($request->input('weight_variants', []));
        foreach ($weights as $i => $row) {
            $val = trim((string) ($row['value'] ?? ''));
            if ($val === '') {
                continue;
            }

            $imagePath = ! empty($row['image_path']) ? trim((string) $row['image_path']) : null;
            if (! empty($row['image_b64'])) {
                $name = (string) ($row['image_name'] ?? ('weight_'.$i.'.jpg'));
                $decoded = PublicUploader::decodeBase64Payload((string) $row['image_b64'], $name, 'variant_image');
                if ($decoded) {
                    $imagePath = PublicUploader::storeBytes($decoded[0], 'products/variants', $decoded[1], 'variant_image');
                }
            }

            ProductVariant::create([
                'product_id' => $product->id,
                'type' => 'Weight',
                'value' => $val,
                'color_code' => null,
                'price_delta' => (float) ($row['price_delta'] ?? 0),
                'stock' => max(0, (int) ($row['stock'] ?? 0)),
                'image_path' => $imagePath,
                'position' => $pos++,
            ]);
        }

        // 4. Custom Variant Groups (e.g. Storage, Edition, Pack, Material, etc.)
        $customGroups = collect($request->input('custom_variant_groups', []));
        foreach ($customGroups as $gIndex => $group) {
            $groupName = trim((string) ($group['name'] ?? ''));
            if ($groupName === '') {
                continue;
            }

            $options = collect($group['options'] ?? []);
            foreach ($options as $optIndex => $opt) {
                $val = trim((string) ($opt['value'] ?? ''));
                if ($val === '') {
                    continue;
                }

                $imagePath = ! empty($opt['image_path']) ? trim((string) $opt['image_path']) : null;
                if (! empty($opt['image_b64'])) {
                    $name = (string) ($opt['image_name'] ?? ("custom_{$gIndex}_{$optIndex}.jpg"));
                    $decoded = PublicUploader::decodeBase64Payload((string) $opt['image_b64'], $name, 'variant_image');
                    if ($decoded) {
                        $imagePath = PublicUploader::storeBytes($decoded[0], 'products/variants', $decoded[1], 'variant_image');
                    }
                }

                ProductVariant::create([
                    'product_id' => $product->id,
                    'type' => $groupName,
                    'value' => $val,
                    'color_code' => null,
                    'price_delta' => (float) ($opt['price_delta'] ?? 0),
                    'stock' => max(0, (int) ($opt['stock'] ?? 0)),
                    'image_path' => $imagePath,
                    'position' => $pos++,
                ]);
            }
        }
    }

    private function storeImages(Product $product, Request $request): void
    {
        $paths = PublicUploader::storeManyFromRequest($request, 'images', 'products');
        if ($paths === []) {
            return;
        }

        $hasPrimary = $product->images()->where('is_primary', true)->exists();
        $position = (int) $product->images()->max('position');

        foreach ($paths as $path) {
            ProductImage::create([
                'product_id' => $product->id,
                'path' => $path,
                'alt' => $product->name,
                'is_primary' => ! $hasPrimary,
                'position' => ++$position,
            ]);
            $hasPrimary = true;
        }
    }

    private function deletePublicUpload(?string $path): void
    {
        PublicUploader::delete($path);
    }
}
