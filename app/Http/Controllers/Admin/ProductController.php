<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\DropshipProductLink;
use App\Models\DropshipSupplierProduct;
use App\Support\PublicUploader;
use App\Services\ProductPricingService;
use App\Services\ProductCatalogService;
use App\Services\CategoryHierarchyService;
use App\Models\DropshipSupplier;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function index(Request $request, ProductCatalogService $catalog)
    {
        $filters = $catalog->filters($request);
        $query = $catalog->query($filters);
        $totals = (clone $query)->selectRaw('COUNT(*) AS total, SUM(CASE WHEN is_published = 1 THEN 1 ELSE 0 END) AS published, SUM(CASE WHEN is_published = 0 THEN 1 ELSE 0 END) AS draft')->first();
        $catalog->visibility($query, $filters['status']);
        $catalog->sort($query, $filters);
        return Inertia::render('Admin/Products/Index', [
            'products' => $query->with('category', 'images', 'supplier:id,name', 'supplierLinks.supplierProduct.supplier')->withCount('variants')
                ->paginate($filters['per_page'])->appends($catalog->parameters($filters))->through(fn ($product) => [
                    'id' => $product->id, 'name' => $product->name, 'sku' => $product->sku, 'brand' => $product->brand,
                    'url_key' => $product->url_key, 'is_published' => $product->is_published,
                    'price' => $product->sale_price ?? $product->regular_price, 'regular_price' => $product->regular_price, 'sale_price' => $product->sale_price,
                    ...app(ProductPricingService::class)->inventoryDetails($product),
                    'stock_quantity' => (int) $product->stock_quantity, 'has_variants' => $product->variants_count > 0,
                    'variants_count' => (int) $product->variants_count, 'image_url' => $product->imageUrl(),
                    'category' => $product->category ? ['name' => $product->category->name] : null,
                    'supplier' => $product->supplier?->name ?: $product->supplierLinks->first()?->supplierProduct?->supplier?->name,
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
            'product'    => ['is_published' => true, 'regular_price' => 0, 'stock_quantity' => 0, 'specifications' => [], 'variants' => [], 'images' => []],
            'categories' => app(CategoryHierarchyService::class)->options(),
            'suppliers'  => $this->supplierOptions(),
            'returnTo' => ProductCatalogService::returnUrl($request->input('return_to')),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $this->validateSupplierAssignment($data['supplier_id'] ?? null);
        $data = $this->sanitizeProductData($data, $request);
        $data['slug'] = ($data['slug'] ?? '') ?: $data['name'];
        $this->applyFlags($request, $data, null);
        $data['specifications'] = $this->normalizeSpecifications($request);

        $data = app(ProductPricingService::class)->prepare($data);
        $product = Product::create($data);
        $this->syncVariants($product, $request);
        $this->storeImages($product, $request);

        return redirect()->route('admin.products.edit', ['product' => $product->id, ...($request->filled('return_to') ? ['return_to' => ProductCatalogService::returnUrl($request->input('return_to'))] : [])])->with('status', 'Product created.');
    }

    public function edit(Request $request, Product $product)
    {
        $product->load('images', 'variants', 'supplier:id,name,key,driver_key,is_active');

        // Group variants
        $variants = $product->variants->map(fn($v) => [
            'id'          => $v->id,
            'type'        => $v->type,
            'value'       => $v->value,
            'color_code'  => $v->color_code,
            'price_delta' => $v->price_delta,
            'stock'       => $v->stock,
            'image_path'  => $v->image_path,
        ])->values();

        // Custom variant groups (any type other than Size, Color, Weight)
        $customGroups = $product->variants
            ->whereNotIn('type', ['Size', 'Color', 'Weight'])
            ->groupBy('type')
            ->map(fn($items, $type) => [
                'name'    => $type,
                'options' => $items->map(fn($v) => [
                    'id'          => $v->id,
                    'value'       => $v->value,
                    'price_delta' => $v->price_delta,
                    'stock'       => $v->stock,
                    'image_path'  => $v->image_path,
                ])->values(),
            ])
            ->values();

        return Inertia::render('Admin/Products/Form', [
            'product'    => array_merge($product->toArray(), [
                ...$product->only(['selling_price', 'minimum_price', 'maximum_price', 'pricing_mode', 'enforce_minimum_price', 'enforce_maximum_price', 'protect_minimum_price']),
                'images' => $product->images->map(fn($img) => ['id' => $img->id, 'path' => $img->path, 'alt' => $img->alt, 'is_primary' => $img->is_primary])->values(),
                'variants' => $variants,
                'custom_variant_groups' => $customGroups,
                'specifications' => $product->specifications ?? [],
            ]),
            'categories' => app(CategoryHierarchyService::class)->options(),
            'suppliers'  => $this->supplierOptions(),
            'pricing' => app(ProductPricingService::class)->context($product),
            'returnTo' => ProductCatalogService::returnUrl($request->input('return_to')),
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $data = $this->validateData($request, $product);
        if ((int) ($data['supplier_id'] ?? 0) !== (int) ($product->supplier_id ?? 0)) {
            $this->validateSupplierAssignment($data['supplier_id'] ?? null);
        }
        $data = $this->sanitizeProductData($data, $request);
        $data['slug'] = ($data['slug'] ?? '') ?: $product->slug;
        $this->applyFlags($request, $data, $product);
        $data['specifications'] = $this->normalizeSpecifications($request);

        DB::transaction(function () use ($product, $data) {
            $links = $product->supplierLinks()->orderBy('id')->get(['id', 'supplier_product_row_id']);
            // Match the supplier sync lock order so an edit and sync cannot
            // overwrite each other or deadlock over product/link rows.
            DropshipSupplierProduct::whereIn('id', $links->pluck('supplier_product_row_id'))->orderBy('id')->lockForUpdate()->get();
            DropshipProductLink::whereIn('id', $links->pluck('id'))->orderBy('id')->lockForUpdate()->get();
            $product = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            $data = app(ProductPricingService::class)->prepare($data, $product);
            $product->update($data);
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
            'ids'         => ['required', 'array', 'min:1', 'max:100'],
            'ids.*'       => ['integer', 'distinct', 'exists:products,id'],
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
    private function validateData(Request $request, ?Product $product = null): array
    {
        return $request->validate([
            'product_type'                   => ['nullable', 'string', 'in:simple,variable'],
            'category_id'                    => ['required', 'exists:categories,id'],
            'supplier_id'                    => ['nullable', 'integer', 'exists:dropship_suppliers,id'],
            'source_type'                    => ['nullable', 'string', 'in:manual,dropshipping,other'],
            'name'                           => ['required', 'string', 'max:180'],
            'slug'                           => ['nullable', 'string', 'max:180'],
            'sku'                            => ['nullable', 'string', 'max:60'],
            'brand'                          => ['nullable', 'string', 'max:80'],
            'short_description'              => ['nullable', 'string'],
            'description'                    => ['nullable', 'string'],
            'regular_price'                  => ['nullable', 'numeric', 'min:0'],
            'sale_price'                     => ['nullable', 'numeric', 'min:0'],
            'selling_price'                  => ['nullable', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'minimum_price'                  => ['nullable', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'maximum_price'                  => ['nullable', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'pricing_mode'                   => ['sometimes', 'required', 'in:auto,manual'],
            'enforce_minimum_price'           => ['sometimes', 'boolean'],
            'enforce_maximum_price'           => ['sometimes', 'boolean'],
            'protect_minimum_price'           => ['sometimes', 'boolean'],
            'stock_quantity'                 => ['nullable', 'integer', 'min:0'],
            'unit'                           => ['nullable', 'string', 'max:40'],
            'meta_title'                     => ['nullable', 'string', 'max:180'],
            'meta_description'               => ['nullable', 'string', 'max:500'],
            'meta_keywords'                  => ['nullable', 'string', 'max:500'],
            'size_variants'                  => ['nullable', 'array'],
            'size_variants.*.value'          => ['nullable', 'string', 'max:60'],
            'size_variants.*.price_delta'    => ['nullable', 'numeric'],
            'size_variants.*.stock'          => ['nullable', 'integer', 'min:0'],
            'size_variants.*.image_path'     => ['nullable', 'string', 'max:500'],
            'size_variants.*.image_b64'      => ['nullable', 'string'],
            'size_variants.*.image_name'     => ['nullable', 'string', 'max:255'],
            'color_variants'                 => ['nullable', 'array'],
            'color_variants.*.value'         => ['nullable', 'string', 'max:60'],
            'color_variants.*.color_code'    => ['nullable', 'string', 'max:30'],
            'color_variants.*.price_delta'   => ['nullable', 'numeric'],
            'color_variants.*.stock'         => ['nullable', 'integer', 'min:0'],
            'color_variants.*.image_path'    => ['nullable', 'string', 'max:500'],
            'color_variants.*.image_b64'     => ['nullable', 'string'],
            'color_variants.*.image_name'    => ['nullable', 'string', 'max:255'],
            'weight_variants'                => ['nullable', 'array'],
            'weight_variants.*.value'        => ['nullable', 'string', 'max:60'],
            'weight_variants.*.price_delta'  => ['nullable', 'numeric'],
            'weight_variants.*.stock'        => ['nullable', 'integer', 'min:0'],
            'weight_variants.*.image_path'   => ['nullable', 'string', 'max:500'],
            'weight_variants.*.image_b64'    => ['nullable', 'string'],
            'weight_variants.*.image_name'   => ['nullable', 'string', 'max:255'],
            'custom_variant_groups'          => ['nullable', 'array'],
            'custom_variant_groups.*.name'   => ['nullable', 'string', 'max:60'],
            'custom_variant_groups.*.options'=> ['nullable', 'array'],
            'custom_variant_groups.*.options.*.value'       => ['nullable', 'string', 'max:60'],
            'custom_variant_groups.*.options.*.price_delta' => ['nullable', 'numeric'],
            'custom_variant_groups.*.options.*.stock'       => ['nullable', 'integer', 'min:0'],
            'custom_variant_groups.*.options.*.image_path'  => ['nullable', 'string', 'max:500'],
            'custom_variant_groups.*.options.*.image_b64'   => ['nullable', 'string'],
            'custom_variant_groups.*.options.*.image_name'  => ['nullable', 'string', 'max:255'],
            'spec_labels'                    => ['nullable', 'array'],
            'spec_labels.*'                  => ['nullable', 'string', 'max:120'],
            'spec_values'                    => ['nullable', 'array'],
            'spec_values.*'                  => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function sanitizeProductData(array $data, ?Request $request = null): array
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

        $data['sale_price'] = (isset($data['sale_price']) && trim((string) $data['sale_price']) !== '')
            ? (float) $data['sale_price']
            : null;

        $regularPrice = (float) ($data['regular_price'] ?? 0);
        $stockQuantity = max(0, (int) ($data['stock_quantity'] ?? 0));

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

    private function supplierOptions(): array
    {
        return DropshipSupplier::query()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get(['id', 'name', 'key', 'driver_key', 'supplier_type', 'is_active'])
            ->map(fn (DropshipSupplier $supplier): array => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'key' => $supplier->key,
                'driver_key' => $supplier->driver_key,
                'supplier_type' => $supplier->supplier_type,
                'is_active' => $supplier->is_active,
            ])->values()->all();
    }

    private function validateSupplierAssignment(?int $supplierId): void
    {
        if ($supplierId === null) return;
        $supplier = DropshipSupplier::query()->find($supplierId);
        if (! $supplier || ! $supplier->is_active) {
            throw ValidationException::withMessages(['supplier_id' => 'Select an active supplier.']);
        }
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
        $data['is_published']     = $request->boolean('is_published');
        $data['is_featured']      = $request->boolean('is_featured');
        $data['is_new_arrival']   = $request->boolean('is_new_arrival');
        $data['is_best_seller']   = $request->boolean('is_best_seller');
        $data['is_flash_sale']    = $request->boolean('is_flash_sale');
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
            if ($val === '') continue;

            $imagePath = ! empty($row['image_path']) ? trim((string) $row['image_path']) : null;
            if (! empty($row['image_b64'])) {
                $name = (string) ($row['image_name'] ?? ('color_' . $i . '.jpg'));
                $decoded = PublicUploader::decodeBase64Payload((string) $row['image_b64'], $name, 'variant_image');
                if ($decoded) {
                    $imagePath = PublicUploader::storeBytes($decoded[0], 'products/variants', $decoded[1], 'variant_image');
                }
            }

            $colorCode = ! empty($row['color_code']) ? trim((string) $row['color_code']) : null;

            ProductVariant::create([
                'product_id'  => $product->id,
                'type'        => 'Color',
                'value'       => $val,
                'color_code'  => $colorCode,
                'price_delta' => (float) ($row['price_delta'] ?? 0),
                'stock'       => max(0, (int) ($row['stock'] ?? 0)),
                'image_path'  => $imagePath,
                'position'    => $pos++,
            ]);
        }

        // 2. Size Variants
        $sizes = collect($request->input('size_variants', []));
        foreach ($sizes as $i => $row) {
            $val = trim((string) ($row['value'] ?? ''));
            if ($val === '') continue;

            $imagePath = ! empty($row['image_path']) ? trim((string) $row['image_path']) : null;
            if (! empty($row['image_b64'])) {
                $name = (string) ($row['image_name'] ?? ('size_' . $i . '.jpg'));
                $decoded = PublicUploader::decodeBase64Payload((string) $row['image_b64'], $name, 'variant_image');
                if ($decoded) {
                    $imagePath = PublicUploader::storeBytes($decoded[0], 'products/variants', $decoded[1], 'variant_image');
                }
            }

            ProductVariant::create([
                'product_id'  => $product->id,
                'type'        => 'Size',
                'value'       => $val,
                'color_code'  => null,
                'price_delta' => (float) ($row['price_delta'] ?? 0),
                'stock'       => max(0, (int) ($row['stock'] ?? 0)),
                'image_path'  => $imagePath,
                'position'    => $pos++,
            ]);
        }

        // 3. Weight / Legacy Variants
        $weights = collect($request->input('weight_variants', []));
        foreach ($weights as $i => $row) {
            $val = trim((string) ($row['value'] ?? ''));
            if ($val === '') continue;

            $imagePath = ! empty($row['image_path']) ? trim((string) $row['image_path']) : null;
            if (! empty($row['image_b64'])) {
                $name = (string) ($row['image_name'] ?? ('weight_' . $i . '.jpg'));
                $decoded = PublicUploader::decodeBase64Payload((string) $row['image_b64'], $name, 'variant_image');
                if ($decoded) {
                    $imagePath = PublicUploader::storeBytes($decoded[0], 'products/variants', $decoded[1], 'variant_image');
                }
            }

            ProductVariant::create([
                'product_id'  => $product->id,
                'type'        => 'Weight',
                'value'       => $val,
                'color_code'  => null,
                'price_delta' => (float) ($row['price_delta'] ?? 0),
                'stock'       => max(0, (int) ($row['stock'] ?? 0)),
                'image_path'  => $imagePath,
                'position'    => $pos++,
            ]);
        }

        // 4. Custom Variant Groups (e.g. Storage, Edition, Pack, Material, etc.)
        $customGroups = collect($request->input('custom_variant_groups', []));
        foreach ($customGroups as $gIndex => $group) {
            $groupName = trim((string) ($group['name'] ?? ''));
            if ($groupName === '') continue;

            $options = collect($group['options'] ?? []);
            foreach ($options as $optIndex => $opt) {
                $val = trim((string) ($opt['value'] ?? ''));
                if ($val === '') continue;

                $imagePath = ! empty($opt['image_path']) ? trim((string) $opt['image_path']) : null;
                if (! empty($opt['image_b64'])) {
                    $name = (string) ($opt['image_name'] ?? ("custom_{$gIndex}_{$optIndex}.jpg"));
                    $decoded = PublicUploader::decodeBase64Payload((string) $opt['image_b64'], $name, 'variant_image');
                    if ($decoded) {
                        $imagePath = PublicUploader::storeBytes($decoded[0], 'products/variants', $decoded[1], 'variant_image');
                    }
                }

                ProductVariant::create([
                    'product_id'  => $product->id,
                    'type'        => $groupName,
                    'value'       => $val,
                    'color_code'  => null,
                    'price_delta' => (float) ($opt['price_delta'] ?? 0),
                    'stock'       => max(0, (int) ($opt['stock'] ?? 0)),
                    'image_path'  => $imagePath,
                    'position'    => $pos++,
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
                'path'       => $path,
                'alt'        => $product->name,
                'is_primary' => ! $hasPrimary,
                'position'   => ++$position,
            ]);
            $hasPrimary = true;
        }
    }

    private function deletePublicUpload(?string $path): void
    {
        PublicUploader::delete($path);
    }
}
