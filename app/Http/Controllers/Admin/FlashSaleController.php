<?php

namespace App\Http\Controllers\Admin;

use Inertia\Inertia;
use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Throwable;

class FlashSaleController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->input('q', ''));
        $category = $request->input('category');
        $productStatus = $request->input('product_status', $request->input('status', 'all'));
        $imageType = $request->input('image_type', 'all');
        $bannerUsageFilter = $request->input('banner_usage', 'all');
        $stockOperator = $request->input('stock_operator', 'any');
        $stockValue = $request->input('stock_value');
        $priceAdjustmentFilter = $request->input('price_adjustment', '');
        $perPage = (int) $request->input('per_page', 24);

        $category = is_numeric($category) ? (int) $category : null;
        $productStatus = match ($productStatus) {
            'active', 'published' => 'published',
            'inactive', 'unpublished' => 'unpublished',
            default => 'all',
        };
        $imageType = in_array($imageType, ['all', 'primary'], true) ? $imageType : 'all';
        $bannerUsageFilter = in_array($bannerUsageFilter, ['all', 'hero', 'hero_side', 'middle'], true)
            ? $bannerUsageFilter
            : 'all';
        $stockOperator = in_array($stockOperator, ['any', 'in_stock', 'out_of_stock', 'gt', 'gte', 'eq', 'lte', 'lt'], true)
            ? $stockOperator
            : 'any';
        $stockValue = is_numeric($stockValue) ? max(0, min(1000000, (int) $stockValue)) : null;
        $priceAdjustmentFilter = in_array($priceAdjustmentFilter, ['custom', 'fixed', 'percent', 'regular', 'untracked_discount', 'none'], true)
            ? $priceAdjustmentFilter
            : '';
        $perPage = in_array($perPage, [24, 48, 100], true) ? $perPage : 24;

        $flashProducts = Product::with('images', 'category', 'supplierLinks.supplierProduct.supplier')
            ->where('is_flash_sale', true)
            ->orderBy('flash_sale_position')
            ->orderBy('id')
            ->get();

        $available = Product::with('images', 'category', 'supplierLinks.supplierProduct.supplier')
            ->where('is_flash_sale', false)
            ->when($productStatus === 'published', fn ($q) => $q->where('is_published', true))
            ->when($productStatus === 'unpublished', fn ($q) => $q->where('is_published', false))
            ->when($category, fn ($q) => $q->where('category_id', $category))
            ->when($imageType === 'primary', fn ($q) => $q->whereHas('images', fn ($image) => $image->where('is_primary', true)))
            ->when($priceAdjustmentFilter === 'custom', fn ($q) => $q->whereHas('supplierLinks', fn ($linkQuery) => $linkQuery->whereNotNull('price_override')))
            ->when($priceAdjustmentFilter === 'fixed', fn ($q) => $q->whereHas('supplierLinks', fn ($linkQuery) => $linkQuery->where('price_override->discount_mode', 'fixed')))
            ->when($priceAdjustmentFilter === 'percent', fn ($q) => $q->whereHas('supplierLinks', fn ($linkQuery) => $linkQuery->where('price_override->discount_mode', 'percent')))
            ->when($priceAdjustmentFilter === 'regular', fn ($q) => $q->whereHas('supplierLinks', fn ($linkQuery) => $linkQuery
                ->whereNotNull('price_override')
                ->where('price_override->regular_mode', '!=', 'none')))
            ->when($priceAdjustmentFilter === 'untracked_discount', fn ($q) => $q
                ->whereNotNull('sale_price')
                ->whereColumn('sale_price', '<', 'regular_price')
                ->whereDoesntHave('supplierLinks', fn ($linkQuery) => $linkQuery->whereNotNull('price_override')))
            ->when($priceAdjustmentFilter === 'none', fn ($q) => $q->whereDoesntHave('supplierLinks', fn ($linkQuery) => $linkQuery->whereNotNull('price_override')))
            ->when($bannerUsageFilter !== 'all', function ($q) use ($bannerUsageFilter) {
                $q->whereHas('images', fn ($image) => $image->whereIn('path', Banner::query()
                    ->select('image')
                    ->where('placement', $bannerUsageFilter)
                    ->whereNotNull('image')));
            })
            ->when($stockOperator === 'in_stock', function ($q) {
                $q->where(function ($stockQuery) {
                    $stockQuery->where('stock_quantity', '>', 0)
                        ->orWhereHas('variants', fn ($variantQuery) => $variantQuery->where('stock', '>', 0));
                });
            })
            ->when($stockOperator === 'out_of_stock', function ($q) {
                $q->where('stock_quantity', '<=', 0)
                    ->whereDoesntHave('variants', fn ($variantQuery) => $variantQuery->where('stock', '>', 0));
            })
            ->when($stockValue !== null && in_array($stockOperator, ['gt', 'gte', 'eq', 'lte', 'lt'], true), function ($q) use ($stockOperator, $stockValue) {
                $operator = ['gt' => '>', 'gte' => '>=', 'eq' => '=', 'lte' => '<=', 'lt' => '<'][$stockOperator];
                $q->where('stock_quantity', $operator, $stockValue);
            })
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%")
                        ->orWhereHas('images', fn ($image) => $image->where('alt', 'like', "%{$search}%"))
                        ->orWhereHas('supplierLinks.supplierProduct', function ($supplierProduct) use ($search) {
                            $supplierProduct->where('name', 'like', "%{$search}%")
                                ->orWhere('product_code', 'like', "%{$search}%")
                                ->orWhere('supplier_product_id', 'like', "%{$search}%")
                                ->orWhere('supplier_category_key', 'like', "%{$search}%")
                                ->orWhere('brand', 'like', "%{$search}%")
                                ->orWhereHas('supplier', function ($supplier) use ($search) {
                                    $supplier->where('name', 'like', "%{$search}%")
                                        ->orWhere('key', 'like', "%{$search}%");
                                });
                        });
                });
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        $endsAt = setting('flash_sale_ends_at');

        return Inertia::render('Admin/FlashSale/Index', [
            'flashProducts' => $flashProducts,
            'available'     => $available,
            'categories'    => Category::orderBy('name')->get(['id', 'name']),
            'q'             => $request->input('q'),
            'filters'       => [
                'category'        => $category,
                'product_status'  => $productStatus,
                'image_type'      => $imageType,
                'banner_usage'    => $bannerUsageFilter,
                'stock_operator'  => $stockOperator,
                'stock_value'     => $stockValue,
                'price_adjustment' => $priceAdjustmentFilter,
                'per_page'        => $perPage,
            ],
            'endsAt'        => $endsAt,
            'timerExpired'  => $this->timerExpired($endsAt),
            'homepageLimit' => 5,
        ]);
    }

    public function updateEndsAt(Request $request)
    {
        $data = $request->validate([
            'flash_sale_ends_at' => ['nullable', 'date', 'after:now'],
        ]);

        $raw = (string) ($data['flash_sale_ends_at'] ?? '');
        if ($raw !== '') {
            $raw = str_replace('T', ' ', $raw);
            if (strlen($raw) === 16) {
                $raw .= ':00';
            }
        }

        Setting::put('flash_sale_ends_at', $raw);
        Setting::forgetCache();

        return back()->with('status', 'Flash sale end time saved.');
    }

    public function add(Product $product)
    {
        $nextPos = (int) Product::where('is_flash_sale', true)->max('flash_sale_position') + 1;

        $product->update([
            'is_flash_sale'       => true,
            'flash_sale_position' => $nextPos,
        ]);

        return back()->with('status', "“{$product->name}” added to Flash Sale.");
    }

    public function remove(Product $product)
    {
        $product->update([
            'is_flash_sale'       => false,
            'flash_sale_position' => 0,
        ]);

        return back()->with('status', "“{$product->name}” removed from Flash Sale.");
    }

    public function bulk(Request $request)
    {
        $data = $request->validate([
            'ids'         => ['required', 'array', 'min:1'],
            'ids.*'       => ['integer', 'exists:products,id'],
            'bulk_action' => ['required', 'in:add,remove'],
        ]);

        $ids = $data['ids'];
        $count = count($ids);

        if ($data['bulk_action'] === 'add') {
            $nextPos = (int) Product::where('is_flash_sale', true)->max('flash_sale_position');
            foreach (Product::whereIn('id', $ids)->where('is_flash_sale', false)->orderBy('id')->get() as $product) {
                $product->update([
                    'is_flash_sale'       => true,
                    'flash_sale_position' => ++$nextPos,
                ]);
            }

            return back()->with('status', "{$count} product(s) added to Flash Sale.");
        }

        Product::whereIn('id', $ids)->where('is_flash_sale', true)->update([
            'is_flash_sale'       => false,
            'flash_sale_position' => 0,
        ]);

        return back()->with('status', "{$count} product(s) removed from Flash Sale.");
    }

    public function reorder(Request $request)
    {
        $data = $request->validate([
            'order'   => ['required', 'array', 'min:1'],
            'order.*' => ['integer', 'exists:products,id'],
        ]);

        foreach (array_values($data['order']) as $i => $id) {
            Product::where('id', $id)->where('is_flash_sale', true)->update([
                'flash_sale_position' => $i,
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Flash sale order updated.']);
        }

        return back()->with('status', 'Flash sale order updated.');
    }

    private function timerExpired(?string $endsAt): bool
    {
        $value = trim((string) $endsAt);

        if ($value === '') {
            return false;
        }

        try {
            return Carbon::parse($value)->isPast();
        } catch (Throwable) {
            return false;
        }
    }
}
