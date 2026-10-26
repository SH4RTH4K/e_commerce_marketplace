<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CategoryHierarchyService;
use App\Services\CategorySeoService;
use App\Services\StorefrontSeoService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Throwable;

class ShopController extends Controller
{
    public function index(Request $request, ?Category $category = null)
    {
        $categoryPath = $category ? app(CategoryHierarchyService::class)->path($category) : [];
        if ($category) {
            abort_if($categoryPath === [] || collect($categoryPath)->contains(fn ($item) => ! $item['is_active']), 404);
        }
        $categoryIds = $category?->selfAndDescendantIds(true);

        $query = Product::published()
            // Listing cards do not need full descriptions, specifications, or
            // supplier payloads. Keep those on the individual product page.
            ->select([
                'id', 'category_id', 'name', 'slug', 'sku', 'brand',
                'regular_price', 'sale_price', 'stock_quantity', 'allow_backorder', 'sourced_stock_quantity', 'unit',
                'rating', 'reviews_count', 'is_featured', 'is_new_arrival',
                'is_best_seller', 'is_flash_sale', 'is_free_shipping',
                'created_at', 'updated_at',
            ])
            ->with([
                'images:id,product_id,path,is_primary,position,updated_at',
                'category:id,name,slug',
                'supplierLinks:id,product_id,supplier_product_row_id',
                'supplierLinks.supplierProduct:id,product_code,supplier_product_id',
            ])
            ->withExists('variants')
            ->withExists([
                'variants as variants_in_stock_exists' => fn ($variantQuery) => $variantQuery->where('stock', '>', 0),
            ]);

        if ($categoryIds) {
            $query->whereIn('category_id', $categoryIds);
        }

        if ($term = trim((string) $request->input('q'))) {
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('brand', 'like', "%{$term}%")
                    ->orWhere('short_description', 'like', "%{$term}%")
                    ->orWhere('sku', 'like', "%{$term}%")
                    ->orWhereHas('supplierLinks.supplierProduct', fn ($supplierProductQuery) => $supplierProductQuery
                        ->where('supplier_product_id', 'like', "%{$term}%")
                        ->orWhere('product_code', 'like', "%{$term}%"));
            });
        }

        if ($request->filled('min')) {
            $query->whereRaw('COALESCE(sale_price, regular_price) >= ?', [(float) $request->input('min')]);
        }
        if ($request->filled('max')) {
            $query->whereRaw('COALESCE(sale_price, regular_price) <= ?', [(float) $request->input('max')]);
        }

        // Flash Sale page: only flash products, ordered by admin flash-sale order
        $isFlashPage = $request->boolean('flash');
        $flashSaleActive = $this->flashSaleTimerActive();
        if ($isFlashPage) {
            $query->where('is_flash_sale', true);
            if (! $flashSaleActive) {
                $query->whereRaw('1 = 0');
            }
        } elseif ($request->boolean('on_sale')) {
            $query->where(function ($q) use ($flashSaleActive) {
                $q->when($flashSaleActive, fn ($flashQuery) => $flashQuery->where('is_flash_sale', true))
                    ->orWhere(function ($q2) {
                        $q2->whereNotNull('sale_price')
                            ->whereColumn('sale_price', '<', 'regular_price');
                    });
            });
        }
        $inStock = $request->boolean('in_stock');
        $outOfStock = $request->boolean('out_of_stock');
        if ($inStock && ! $outOfStock) {
            $query->where(function ($q) {
                $q->where('stock_quantity', '>', 0)
                    ->orWhere('sourced_stock_quantity', '>', 0)
                    ->orWhere('allow_backorder', true);
            });
        } elseif ($outOfStock && ! $inStock) {
            $query->where('stock_quantity', '<=', 0)
                ->where('sourced_stock_quantity', '<=', 0)
                ->where('allow_backorder', false);
        }
        if ($request->boolean('featured')) {
            $query->where('is_featured', true);
        }
        if ($request->boolean('new')) {
            $query->where('is_new_arrival', true);
        }
        if ($request->boolean('best')) {
            $query->where('is_best_seller', true);
        }

        if ($brand = trim((string) $request->input('brand'))) {
            $query->where('brand', $brand);
        }

        if ($request->filled('min_rating')) {
            // Round average so 4.5–5.0 counts as 5★ (& Up), 3.5–4.4 as 4★, etc.
            $minStars = max(1, min(5, (int) $request->input('min_rating')));
            $query->whereRaw('ROUND(rating) >= ?', [$minStars]);
        }

        $selectedVariants = collect((array) $request->input('variants', []))
            ->map(fn ($values) => collect((array) $values)
                ->map(fn ($value) => trim((string) $value))
                ->filter()
                ->unique()
                ->values()
                ->all())
            ->filter(fn ($values, $type) => trim((string) $type) !== '' && count($values) > 0)
            ->all();

        foreach ($selectedVariants as $type => $values) {
            $query->whereHas('variants', function ($variantQuery) use ($type, $values) {
                $variantQuery->where('type', (string) $type)
                    ->whereIn('value', $values);
            });
        }

        $requestedSort = (string) $request->input('sort', '');
        $sort = $requestedSort !== '' ? $requestedSort : 'popular';
        if (! in_array($sort, ['popular', 'newest', 'price_low', 'price_high', 'rating', 'name'], true)) {
            $sort = 'popular';
        }
        if ($isFlashPage && $requestedSort === '') {
            $query->orderBy('flash_sale_position')->orderBy('id');
        } else {
            match ($sort) {
                'price_low' => $query->orderByRaw('COALESCE(sale_price, regular_price) asc')->orderByDesc('id'),
                'price_high' => $query->orderByRaw('COALESCE(sale_price, regular_price) desc')->orderByDesc('id'),
                'rating' => $query->orderByDesc('rating')->orderByDesc('reviews_count')->orderByDesc('id'),
                'name' => $query->orderBy('name')->orderByDesc('id'),
                'newest' => $query->orderByDesc('created_at')->orderByDesc('id'),
                default => $query->orderByDesc('is_best_seller')
                    ->orderByDesc('is_featured')
                    ->orderByDesc('reviews_count')
                    ->orderByDesc('rating')
                    ->orderByDesc('id'),
            };
        }

        $template = setting('storefront_template', 'template-2');
        $productsPerPageKey = $template === 'template-1'
            ? 'template_1_products_per_page'
            : 'template_2_products_per_page';
        $productsPerPage = max(1, min(48, (int) setting($productsPerPageKey, '12')));

        $products = $query->paginate($productsPerPage)->withQueryString();
        abort_if($products->currentPage() > $products->lastPage(), 404);
        $products->getCollection()->each(static function (Product $product): void {
            $supplierProduct = $product->supplierLinks->first()?->supplierProduct;
            $product->sku = $product->sku
                ?: $supplierProduct?->product_code
                ?: $supplierProduct?->supplier_product_id;
            $product->unsetRelation('supplierLinks');
        });

        $isPlainShop = ! $category && ! $term;
        $shopDescription = 'Shop fashion, gadgets, electronics, home essentials and daily-use products at '.site_name().', with reliable delivery across Bangladesh.';
        $categorySeo = $category ? app(CategorySeoService::class)->metadata($category) : null;

        // Resolve sidebar data only when requested. Inertia's Load More
        // requests only products, so it should not rebuild every filter.
        $stats = null;
        $catalogueStats = static function () use (&$stats) {
            return $stats ??= Product::published()
                ->selectRaw('COUNT(*) as total, MAX(COALESCE(sale_price, regular_price)) as max_price')
                ->first();
        };

        $brands = fn () => Product::published()
            ->when($categoryIds, fn ($q) => $q->whereIn('category_id', $categoryIds))
            ->whereNotNull('brand')
            ->where('brand', '!=', '')
            ->orderBy('brand')
            ->distinct()
            ->pluck('brand');

        $variantFilters = fn () => ProductVariant::query()
            ->selectRaw('type, value, COUNT(DISTINCT product_id) as products_count')
            ->whereHas('product', function ($productQuery) use ($categoryIds) {
                $productQuery->published()
                    ->when($categoryIds, fn ($q) => $q->whereIn('category_id', $categoryIds));
            })
            ->whereNotNull('type')
            ->where('type', '!=', '')
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->groupBy('type', 'value')
            ->orderBy('type')
            ->orderBy('value')
            ->get()
            ->groupBy('type')
            ->map(fn ($values, $type) => [
                'type' => $type,
                'options' => $values->map(fn ($row) => [
                    'value' => $row->value,
                    'products_count' => (int) $row->products_count,
                ])->values(),
            ])
            ->values();

        return Inertia::render('Storefront/Shop', [
            'structuredData' => fn () => $category ? app(StorefrontSeoService::class)->breadcrumbs($categoryPath) : null,
            'products' => $products,
            'activeCategory' => $category,
            'categoryPath' => array_map(fn ($item) => collect($item)->only(['id', 'name', 'slug'])->all(), $categoryPath),
            'browseCategories' => fn () => $category ? $category->children()->where('is_active', true)->where('show_in_menu', true)->get(['id', 'name', 'slug']) : [],
            'filterCategories' => fn () => Category::storefrontFilterTree(),
            'allProductsCount' => fn () => (int) $catalogueStats()->total,
            'brands' => $brands,
            'variantFilters' => $variantFilters,
            'sort' => $sort,
            'minRating' => $request->input('min_rating'),
            'q' => $term,
            'priceCeiling' => fn () => max(5000, (int) (ceil(max((int) $catalogueStats()->max_price, 5000) / 50) * 50)),
            'seo' => fn () => [
                'title' => $categorySeo['title'] ?? ($term ? "Search: {$term}" : 'Shop All Products | '.site_name()),
                'description' => $categorySeo['description'] ?? ($isPlainShop ? $shopDescription : null),
                'h1' => $isPlainShop ? 'Shop All Products' : null,
                'keywords' => $categorySeo['keywords'] ?? null,
                'robots' => $term || count(array_diff(array_keys($request->query()), ['page', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'gclid', 'fbclid'])) > 0 ? 'noindex,follow' : 'index,follow',
                'canonical' => $request->url().($products->currentPage() > 1 ? '?page='.$products->currentPage() : ''),
                'image' => $category?->image ? $category->imageUrl() : seo_image_url(),
            ],
        ]);
    }

    private function flashSaleTimerActive(): bool
    {
        $endsAt = trim((string) setting('flash_sale_ends_at', ''));

        if ($endsAt === '') {
            return true;
        }

        try {
            return Carbon::parse($endsAt)->isFuture();
        } catch (Throwable) {
            return true;
        }
    }
}
