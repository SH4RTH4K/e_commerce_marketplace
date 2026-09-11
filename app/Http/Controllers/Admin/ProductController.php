<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Support\PublicUploader;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with('category', 'images', 'variants')->latest();
        $term = trim((string) $request->input('q'));

        if ($term !== '') {
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")->orWhere('sku', 'like', "%{$term}%");
            });
        }
        if ($request->filled('category')) {
            $query->where('category_id', $request->input('category'));
        }
        if ($request->filled('status')) {
            $query->where('is_published', $request->input('status') === 'active');
        }

        return Inertia::render('Admin/Products/Index', [
            'products'   => $query->paginate(15)->withQueryString()->through(fn($p) => [
                'id' => $p->id, 'name' => $p->name, 'sku' => $p->sku, 'is_published' => $p->is_published,
                'regular_price' => $p->regular_price, 'sale_price' => $p->sale_price,
                'stock_quantity' => $p->stock_quantity,
                'has_variants' => $p->variants->isNotEmpty(),
                'category' => $p->category ? ['name' => $p->category->name] : null,
                'primary_image' => $p->images->where('is_primary', true)->first()?->path,
            ]),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'q'          => $term,
            'category'   => $request->input('category'),
            'status'     => $request->input('status'),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Products/Form', [
            'product'    => ['is_published' => true, 'regular_price' => 0, 'stock_quantity' => 0, 'specifications' => [], 'variants' => [], 'images' => []],
            'categories' => Category::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $data = $this->sanitizeProductData($data, $request);
        $data['slug'] = $this->uniqueSlug(($data['slug'] ?? '') ?: $data['name']);
        $this->applyFlags($request, $data, null);
        $data['specifications'] = $this->normalizeSpecifications($request);

        $product = Product::create($data);
        $this->syncVariants($product, $request);
        $this->storeImages($product, $request);

        return redirect()->route('admin.products.edit', $product)->with('status', 'Product created.');
    }

    public function edit(Product $product)
    {
        $product->load('images', 'variants');

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
                'images' => $product->images->map(fn($img) => ['id' => $img->id, 'path' => $img->path, 'alt' => $img->alt, 'is_primary' => $img->is_primary])->values(),
                'variants' => $variants,
                'custom_variant_groups' => $customGroups,
                'specifications' => $product->specifications ?? [],
            ]),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $data = $this->validateData($request, $product);
        $data = $this->sanitizeProductData($data, $request);
        $data['slug'] = $this->uniqueSlug(($data['slug'] ?? '') ?: $data['name'], $product->id);
        $this->applyFlags($request, $data, $product);
        $data['specifications'] = $this->normalizeSpecifications($request);

        $product->update($data);
        $this->syncVariants($product, $request);
        $this->storeImages($product, $request);

        return redirect()->route('admin.products.edit', $product)->with('status', 'Product updated.');
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return redirect()->route('admin.products.index')->with('status', 'Product deleted.');
    }

    public function toggle(Product $product)
    {
        $product->update(['is_published' => ! $product->is_published]);

        return back()->with(
            'status',
            $product->is_published
                ? 'Product is now Active and visible on the storefront.'
                : 'Product is now Inactive and hidden from the storefront.'
        );
    }

    public function bulk(Request $request)
    {
        $data = $request->validate([
            'ids'         => ['required', 'array', 'min:1'],
            'ids.*'       => ['integer', 'exists:products,id'],
            'bulk_action' => ['required', 'in:activate,deactivate,delete'],
        ]);

        $ids = $data['ids'];
        $count = count($ids);

        if ($data['bulk_action'] === 'activate') {
            Product::whereIn('id', $ids)->update(['is_published' => true]);

            return back()->with('status', "{$count} product(s) set to Active.");
        }

        if ($data['bulk_action'] === 'deactivate') {
            Product::whereIn('id', $ids)->update(['is_published' => false]);

            return back()->with('status', "{$count} product(s) set to Inactive.");
        }

        Product::whereIn('id', $ids)->delete();

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
            'name'                           => ['required', 'string', 'max:180'],
            'slug'                           => ['nullable', 'string', 'max:180'],
            'sku'                            => ['nullable', 'string', 'max:60'],
            'brand'                          => ['nullable', 'string', 'max:80'],
            'short_description'              => ['nullable', 'string'],
            'description'                    => ['nullable', 'string'],
            'regular_price'                  => ['nullable', 'numeric', 'min:0'],
            'sale_price'                     => ['nullable', 'numeric', 'min:0'],
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

    private function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: Str::random(8);
        $base = Str::limit($base, 150, '');
        $slug = $base;
        $i = 2;
        while (Product::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
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
