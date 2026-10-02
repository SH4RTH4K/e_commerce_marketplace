<?php

namespace App\Http\Controllers\Admin;

use Inertia\Inertia;


use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Support\PublicUploader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BannerController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->input('q', ''));
        $category = $request->input('category');
        $productStatus = $request->input('status', $request->input('product_status', 'all'));
        $imageType = $request->input('image_type', $request->input('filter', 'all'));
        $bannerUsageFilter = $request->input('banner_usage', $request->input('placement', 'all'));
        $stockOperator = $request->input('stock_operator', 'any');
        $stockValue = $request->input('stock_value');
        $perPage = (int) $request->input('per_page', 24);

        $category = is_numeric($category) ? (int) $category : null;
        $productStatus = match ($productStatus) {
            'active', 'published' => 'published',
            'inactive', 'unpublished' => 'unpublished',
            default => 'all',
        };
        $imageType = in_array($imageType, ['all', 'primary'], true) ? $imageType : 'all';
        $bannerUsageFilter = in_array($bannerUsageFilter, array_keys(Banner::PLACEMENTS), true) ? $bannerUsageFilter : 'all';
        $stockOperator = in_array($stockOperator, ['any', 'in_stock', 'out_of_stock', 'gt', 'gte', 'eq', 'lte', 'lt'], true)
            ? $stockOperator
            : 'any';
        $stockValue = is_numeric($stockValue) ? max(0, min(1000000, (int) $stockValue)) : null;
        $perPage = in_array($perPage, [24, 48, 100], true) ? $perPage : 24;

        $query = Banner::with('product.category')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('subtitle', 'like', "%{$search}%")
                        ->orWhere('badge', 'like', "%{$search}%")
                        ->orWhere('button_text', 'like', "%{$search}%")
                        ->orWhere('link_url', 'like', "%{$search}%")
                        ->orWhere('image', 'like', "%{$search}%")
                        ->orWhereIn('image', ProductImage::query()
                            ->select('path')
                            ->where('alt', 'like', "%{$search}%"))
                        ->orWhereHas('product', function ($product) use ($search) {
                            $product->where('name', 'like', "%{$search}%")
                                ->orWhere('sku', 'like', "%{$search}%")
                                ->orWhere('brand', 'like', "%{$search}%");
                        });
                });
            })
            ->when($category, fn ($q) => $q->whereHas('product', fn ($product) => $product->where('category_id', $category)))
            ->when($productStatus === 'published', fn ($q) => $q->whereHas('product', fn ($product) => $product->where('is_published', true)))
            ->when($productStatus === 'unpublished', fn ($q) => $q->whereHas('product', fn ($product) => $product->where('is_published', false)))
            ->when($imageType === 'primary', fn ($q) => $q->whereIn('image', ProductImage::query()
                ->select('path')
                ->where('is_primary', true)))
            ->when($bannerUsageFilter !== 'all', fn ($q) => $q->where('placement', $bannerUsageFilter))
            ->when($stockOperator === 'in_stock', function ($q) {
                $q->whereHas('product', fn ($product) => $product->where(function ($stockQuery) {
                    $stockQuery->where('stock_quantity', '>', 0)
                        ->orWhereHas('variants', fn ($variantQuery) => $variantQuery->where('stock', '>', 0));
                }));
            })
            ->when($stockOperator === 'out_of_stock', function ($q) {
                $q->whereHas('product', fn ($product) => $product
                    ->where('stock_quantity', '<=', 0)
                    ->whereDoesntHave('variants', fn ($variantQuery) => $variantQuery->where('stock', '>', 0)));
            })
            ->when($stockValue !== null && in_array($stockOperator, ['gt', 'gte', 'eq', 'lte', 'lt'], true), function ($q) use ($stockOperator, $stockValue) {
                $operator = ['gt' => '>', 'gte' => '>=', 'eq' => '=', 'lte' => '<=', 'lt' => '<'][$stockOperator];
                $q->whereHas('product', fn ($product) => $product->where('stock_quantity', $operator, $stockValue));
            })
            ->orderBy('placement')
            ->orderBy('position')
            ->orderBy('id');

        $banners = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Banners/Index', [
            'banners'    => $banners,
            'placements' => Banner::PLACEMENTS,
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'filters'    => [
                'q'              => $search,
                'category'       => $category,
                'product_status' => $productStatus,
                'image_type'     => $imageType,
                'banner_usage'   => $bannerUsageFilter,
                'stock_operator' => $stockOperator,
                'stock_value'    => $stockValue,
                'per_page'       => $perPage,
            ],
            'total'      => Banner::count(),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Banners/Form', [
            'banner'     => new Banner(['is_active' => true, 'placement' => 'hero', 'style' => 'brand', 'position' => 0]),
            'placements' => Banner::PLACEMENTS,
            'styles'     => Banner::STYLES,
            'products'   => $this->bannerProducts(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $data = $this->sanitizeBannerData($data);
        $data['is_active'] = $request->boolean('is_active');
        $this->handleImage($request, $data);

        Banner::create($data);

        return redirect()->route('admin.banners.index')->with('status', 'Banner created.');
    }

    public function edit(Banner $banner)
    {
        return Inertia::render('Admin/Banners/Form', [
            'banner'     => $banner,
            'placements' => Banner::PLACEMENTS,
            'styles'     => Banner::STYLES,
            'products'   => $this->bannerProducts(),
        ]);
    }

    public function update(Request $request, Banner $banner)
    {
        $data = $this->validateData($request);
        $data = $this->sanitizeBannerData($data);
        $data['is_active'] = $request->boolean('is_active');
        $this->handleImage($request, $data, $banner);

        $banner->update($data);

        return redirect()->route('admin.banners.index')->with('status', 'Banner updated.');
    }

    public function toggle(Banner $banner)
    {
        $banner->update(['is_active' => ! $banner->is_active]);

        return back()->with('status', $banner->is_active ? 'Banner is now visible on the storefront.' : 'Banner hidden from the storefront.');
    }

    /** Update visibility for several banner records at once. */
    public function bulkStatus(Request $request)
    {
        $data = $request->validate([
            'ids'         => ['required', 'array', 'min:1', 'max:200'],
            'ids.*'       => ['integer', 'distinct', 'exists:banners,id'],
            'bulk_action' => ['required', 'in:activate,deactivate'],
        ]);

        $active = $data['bulk_action'] === 'activate';
        Banner::whereIn('id', $data['ids'])->update(['is_active' => $active]);

        return back()->with('status', count($data['ids']) . ' banner(s) ' . ($active ? 'activated.' : 'hidden.'));
    }

    /** Apply display placement settings to several banners at once. */
    public function bulkPosition(Request $request)
    {
        $positions = 'top-left,top-center,top-right,center-left,center-center,center-right,bottom-left,bottom-center,bottom-right';
        $data = $request->validate([
            'ids'               => ['required', 'array', 'min:1', 'max:200'],
            'ids.*'             => ['integer', 'distinct', 'exists:banners,id'],
            'text_position'     => ['required', 'in:' . $positions],
            'image_position'    => ['required', 'in:' . $positions],
            'image_orientation' => ['required', 'in:landscape,portrait,square'],
        ]);

        Banner::whereIn('id', $data['ids'])->update([
            'text_position'     => $data['text_position'],
            'image_position'    => $data['image_position'],
            'image_orientation' => $data['image_orientation'],
        ]);

        return back()->with('status', count($data['ids']) . ' banner(s) display position updated.');
    }

    /** Delete several banners at once. */
    public function bulkDelete(Request $request)
    {
        $data = $request->validate([
            'ids'   => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer', 'distinct', 'exists:banners,id'],
        ]);

        Banner::whereIn('id', $data['ids'])->delete();

        return back()->with('status', count($data['ids']) . ' banner(s) deleted.');
    }

    public function destroy(Banner $banner)
    {
        $this->deleteStoredImage($banner->image);
        $banner->delete();

        return redirect()->route('admin.banners.index')->with('status', 'Banner deleted.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'title'       => ['nullable', 'string', 'max:180'],
            'subtitle'    => ['nullable', 'string', 'max:400'],
            'badge'       => ['nullable', 'string', 'max:60'],
            'link_url'    => ['nullable', 'string', 'max:255', 'regex:#^(?:https?://[^\s]+|/[^\s]*)$#i'],
            'product_id'  => ['nullable', 'integer', 'exists:products,id'],
            'button_text' => ['nullable', 'string', 'max:60'],
            'placement'   => ['required', 'in:' . implode(',', array_keys(Banner::PLACEMENTS))],
            'style'       => ['required', 'in:' . implode(',', array_keys(Banner::STYLES))],
            'text_position'  => ['nullable', 'in:top-left,top-center,top-right,center-left,center-center,center-right,bottom-left,bottom-center,bottom-right,left,center,right'],
            'image_position' => ['nullable', 'in:top-left,top-center,top-right,center-left,center-center,center-right,bottom-left,bottom-center,bottom-right,left,center,right'],
            'image_orientation' => ['nullable', 'in:landscape,portrait,square'],
            'position'    => ['nullable', 'integer', 'min:0'],
            'image_file'  => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
            'image_url'   => ['nullable', 'url', 'max:255'],
        ]);
    }

    private function handleImage(Request $request, array &$data, ?Banner $banner = null): void
    {
        unset($data['image_file'], $data['image_url']);

        if ($request->boolean('remove_image')) {
            if ($banner?->image) {
                $this->deleteStoredImage($banner->image);
            }
            $data['image'] = null;
        } elseif ($path = PublicUploader::storeFromRequest($request, 'image_file', 'banners', 'jpg')) {
            if ($banner?->image) {
                $this->deleteStoredImage($banner->image);
            }
            $data['image'] = $path;
        } elseif ($request->filled('image_url')) {
            $data['image'] = $request->input('image_url');
        }
    }

    private function deleteStoredImage(?string $path): void
    {
        if (! $path || str_starts_with($path, 'http')) {
            return;
        }

        $relative = ltrim($path, '/');
        if (str_starts_with($relative, 'uploads/')) {
            PublicUploader::delete($relative);

            return;
        }

        Storage::disk('public')->delete($relative);
    }

    private function sanitizeBannerData(array $data): array
    {
        $data['position'] = (isset($data['position']) && trim((string) $data['position']) !== '')
            ? max(0, (int) $data['position'])
            : 0;

        foreach (['title', 'subtitle', 'badge', 'link_url', 'button_text'] as $field) {
            if (isset($data[$field])) {
                $trimmed = trim((string) $data[$field]);
                $data[$field] = $trimmed !== '' ? $trimmed : null;
            }
        }

        $positions = ['top-left', 'top-center', 'top-right', 'center-left', 'center-center', 'center-right', 'bottom-left', 'bottom-center', 'bottom-right'];
        $legacyPositions = ['left' => 'center-left', 'center' => 'center-center', 'right' => 'center-right'];
        foreach (['text_position' => 'center-left', 'image_position' => 'center-center'] as $field => $default) {
            if (array_key_exists($field, $data)) {
                $raw = (string) ($data[$field] ?? '');
                $value = $legacyPositions[$raw] ?? $raw;
                $data[$field] = in_array($value, $positions, true) ? $value : $default;
            }
        }

        if (array_key_exists('image_orientation', $data)) {
            $data['image_orientation'] = in_array($data['image_orientation'], ['landscape', 'portrait', 'square'], true)
                ? $data['image_orientation']
                : 'landscape';
        }

        return $data;
    }

    private function bannerProducts()
    {
        return Product::query()
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'is_published', 'regular_price', 'sale_price']);
    }
}
