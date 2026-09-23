<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Category;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class MediaController extends Controller
{
    public function index(Request $request)
    {
        $query = ProductImage::with('product:id,name,slug,is_published,stock_quantity')
            ->orderByDesc('created_at');

        $filter = $request->input('filter', 'all');
        $category = $request->input('category');
        $productStatus = $request->input('status', $request->input('product_status', 'all'));
        $bannerUsageFilter = $request->input('banner_usage', 'all');
        $stockOperator = $request->input('stock_operator', 'any');
        $stockValue = $request->input('stock_value');
        $perPage = (int) $request->input('per_page', 24);

        $filter = in_array($filter, ['all', 'primary'], true) ? $filter : 'all';
        $category = is_numeric($category) ? (int) $category : null;
        $productStatus = match ($productStatus) {
            'active', 'published' => 'published',
            'inactive', 'unpublished' => 'unpublished',
            default => 'all',
        };
        $bannerUsageFilter = in_array($bannerUsageFilter, ['all', 'hero', 'middle'], true)
            ? $bannerUsageFilter
            : 'all';
        $stockOperator = in_array($stockOperator, ['any', 'in_stock', 'out_of_stock', 'gt', 'gte', 'eq', 'lte', 'lt'], true)
            ? $stockOperator
            : 'any';
        $stockValue = is_numeric($stockValue) ? max(0, min(1000000, (int) $stockValue)) : null;
        $perPage = in_array($perPage, [24, 48, 100], true) ? $perPage : 24;

        if ($filter === 'primary') {
            $query->where('is_primary', true);
        }

        if ($productStatus === 'published') {
            $query->whereHas('product', fn ($productQuery) => $productQuery->where('is_published', true));
        } elseif ($productStatus === 'unpublished') {
            $query->whereHas('product', fn ($productQuery) => $productQuery->where('is_published', false));
        }

        if ($category) {
            $query->whereHas('product', fn ($productQuery) => $productQuery->where('category_id', $category));
        }

        if ($bannerUsageFilter !== 'all') {
            $query->whereIn('path', Banner::query()
                ->select('image')
                ->where('placement', $bannerUsageFilter)
                ->whereNotNull('image'));
        }

        if ($stockOperator === 'in_stock') {
            $query->whereHas('product', fn ($productQuery) => $productQuery->where(function ($stockQuery) {
                $stockQuery->where('stock_quantity', '>', 0)
                    ->orWhereHas('variants', fn ($variantQuery) => $variantQuery->where('stock', '>', 0));
            }));
        } elseif ($stockOperator === 'out_of_stock') {
            $query->whereHas('product', fn ($productQuery) => $productQuery
                ->where('stock_quantity', '<=', 0)
                ->whereDoesntHave('variants', fn ($variantQuery) => $variantQuery->where('stock', '>', 0)));
        } elseif ($stockValue !== null && in_array($stockOperator, ['gt', 'gte', 'eq', 'lte', 'lt'], true)) {
            $operator = ['gt' => '>', 'gte' => '>=', 'eq' => '=', 'lte' => '<=', 'lt' => '<'][$stockOperator];
            $query->whereHas('product', fn ($productQuery) => $productQuery->where('stock_quantity', $operator, $stockValue));
        }

        if ($request->filled('q')) {
            $term = $request->input('q');
            $query->where(function ($searchQuery) use ($term) {
                $searchQuery->where('alt', 'like', "%{$term}%")
                    ->orWhereHas('product', fn($productQuery) => $productQuery->where('name', 'like', "%{$term}%"));
            });
        }

        $images = $query->paginate($perPage)->withQueryString();
        $paths = $images->getCollection()->pluck('path')->filter()->unique()->values();
        $bannerUsageByPath = Banner::query()
            ->whereIn('image', $paths)
            ->orderBy('placement')
            ->orderBy('position')
            ->orderBy('id')
            ->get(['id', 'image', 'placement', 'position', 'is_active'])
            ->groupBy('image');

        return Inertia::render('Admin/Media/Index', [
            'images' => $images->through(fn($img) => [
                'id'         => $img->id,
                'path'       => $img->path,
                'alt'        => $img->alt,
                'is_primary' => $img->is_primary,
                'created_at' => $img->created_at?->format('d M Y'),
                'banner_usage' => $bannerUsageByPath->get($img->path, collect())->map(fn (Banner $banner) => [
                    'id'        => $banner->id,
                    'placement' => $banner->placement,
                    'position'  => (int) $banner->position,
                    'is_active' => (bool) $banner->is_active,
                ])->values(),
                'product'    => $img->product ? [
                    'id'           => $img->product->id,
                    'name'         => $img->product->name,
                    'is_published' => $img->product->is_published,
                    'stock_quantity' => $img->product->stock_quantity,
                ] : null,
            ]),
            'categories'      => Category::orderBy('name')->get(['id', 'name']),
            'q'               => $request->input('q'),
            'filter'          => $filter,
            'category'        => $category,
            'productStatus'   => $productStatus,
            'bannerUsageFilter' => $bannerUsageFilter,
            'stockOperator'   => $stockOperator,
            'stockValue'      => $stockValue,
            'perPage'         => $perPage,
            'total'           => ProductImage::count(),
        ]);
    }
    public function store(Request $request)
    {
        $path = \App\Support\PublicUploader::storeFromRequest($request, 'image', 'media', 'jpg');
        if (! $path) {
            return back()->withErrors(['image' => 'Please select an image to upload.']);
        }

        $alt = pathinfo((string) ($request->file('image')?->getClientOriginalName() ?? $request->input('image_name', 'media')), PATHINFO_FILENAME);

        ProductImage::create([
            'path' => $path,
            'alt'  => $alt ?: 'Product image',
        ]);

        return back()->with('status', 'Image uploaded successfully.');
    }

    public function destroy(ProductImage $image)
    {
        if (Banner::where('image', $image->path)->exists()) {
            return back()->withErrors(['image' => 'This media image is being used by a banner. Replace or delete the banner first.']);
        }

        // Delete the file from storage if it's a local path
        if (! str_starts_with((string) $image->path, 'http')) {
            $fullPath = public_path($image->path);
            if (is_file($fullPath)) {
                unlink($fullPath);
            }
        }
        $image->delete();

        return back()->with('status', 'Image deleted.');
    }

    /** Convert one or more selected media images into ordered banner entries. */
    public function createBanners(Request $request)
    {
        $data = $request->validate([
            'image_ids'   => ['required', 'array', 'min:1', 'max:50'],
            'image_ids.*' => ['integer', 'distinct', 'exists:product_images,id'],
            'placement'   => ['required', 'in:' . implode(',', array_keys(Banner::PLACEMENTS))],
            'style'       => ['nullable', 'in:' . implode(',', array_keys(Banner::STYLES))],
            'text_position'  => ['nullable', 'in:top-left,top-center,top-right,center-left,center-center,center-right,bottom-left,bottom-center,bottom-right'],
            'image_position' => ['nullable', 'in:top-left,top-center,top-right,center-left,center-center,center-right,bottom-left,bottom-center,bottom-right'],
            'image_orientation' => ['nullable', 'in:landscape,portrait,square'],
        ]);

        $imagesById = ProductImage::with('product:id,name')
            ->whereIn('id', $data['image_ids'])
            ->get()
            ->keyBy('id');

        // Supplier imports can create separate records for the same product.
        // Keep a single banner destination for each normalized product name.
        $selectedProductNames = [];
        $duplicateProductCount = 0;
        $images = collect($data['image_ids'])
            ->map(fn (int $id) => $imagesById->get($id))
            ->filter(function (?ProductImage $image) use (&$selectedProductNames, &$duplicateProductCount): bool {
                if (! $image) {
                    return false;
                }

                $productName = trim((string) $image->product?->name);
                if ($productName === '') {
                    return true;
                }

                $key = strtolower((string) preg_replace('/\s+/', ' ', $productName));
                if (isset($selectedProductNames[$key])) {
                    $duplicateProductCount++;

                    return false;
                }

                $selectedProductNames[$key] = true;

                return true;
            });

        if ($images->isEmpty()) {
            return back()->withErrors(['image_ids' => 'Select at least one media image.']);
        }

        $style = $data['style'] ?? 'brand';
        $textPosition = $data['text_position'] ?? 'center-left';
        $imagePosition = $data['image_position'] ?? 'center-center';
        $imageOrientation = $data['image_orientation'] ?? 'landscape';
        $createdCount = 0;
        $skippedCount = 0;

        DB::transaction(function () use ($images, $data, $style, $textPosition, $imagePosition, $imageOrientation, &$createdCount, &$skippedCount, $duplicateProductCount) {
            $position = (int) Banner::where('placement', $data['placement'])->max('position') + 1;
            $existingPaths = Banner::query()
                ->where('placement', $data['placement'])
                ->whereIn('image', $images->pluck('path'))
                ->pluck('image')
                ->flip()
                ->all();

            $existingProductNames = Banner::query()
                ->with('product:id,name')
                ->where('placement', $data['placement'])
                ->get()
                ->mapWithKeys(function (Banner $banner): array {
                    $name = trim((string) $banner->product?->name);

                    return $name === '' ? [] : [strtolower((string) preg_replace('/\s+/', ' ', $name)) => true];
                })
                ->all();

            $skippedCount += $duplicateProductCount;

            foreach ($images as $image) {
                $productName = trim((string) $image->product?->name);
                $productKey = $productName === ''
                    ? null
                    : strtolower((string) preg_replace('/\s+/', ' ', $productName));

                if (isset($existingPaths[$image->path]) || ($productKey !== null && isset($existingProductNames[$productKey]))) {
                    $skippedCount++;
                    continue;
                }

                $title = Str::limit(trim((string) ($image->alt ?: $image->product?->name ?: 'Banner')), 180, '');

                Banner::create([
                    'title'       => $title,
                    'product_id'  => $image->product_id,
                    'image'       => $image->path,
                    'placement'   => $data['placement'],
                    'style'       => $style,
                    'text_position' => $textPosition,
                    'image_position' => $imagePosition,
                    'image_orientation' => $imageOrientation,
                    'position'    => $position++,
                    'is_active'   => true,
                ]);

                $existingPaths[$image->path] = true;
                if ($productKey !== null) {
                    $existingProductNames[$productKey] = true;
                }
                $createdCount++;
            }
        });

        $type = $data['placement'] === 'hero' ? 'hero slider' : 'middle banner';

        if ($createdCount === 0) {
            return back()->withErrors([
                'image_ids' => 'The selected ' . Str::plural('image', $skippedCount) . ' already ' . ($skippedCount === 1 ? 'exists' : 'exist') . ' in the ' . $type . '.',
            ]);
        }

        $status = $createdCount . ' ' . Str::plural($type, $createdCount) . ' created from Media Manager.';
        if ($skippedCount > 0) {
            $status .= ' ' . $skippedCount . ' already-added ' . Str::plural('image', $skippedCount) . ' skipped.';
        }

        return redirect()->route('admin.banners.index')
            ->with('status', $status);
    }
}
