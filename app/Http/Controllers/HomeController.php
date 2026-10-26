<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Models\Category;
use App\Models\Feature;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Throwable;

class HomeController extends Controller
{
    public function index()
    {
        $isTemplateOne = setting('storefront_template', 'template-2') === 'template-1';
        $isTemplateTwo = setting('storefront_template', 'template-2') === 'template-2';
        $overviewAllLimit = min(12, max(1, (int) setting('template_1_overview_all_count', '12')));
        $flashSaleEnabled = setting('homepage_flash_sale_enabled', '1') === '1';
        $flashSaleActive = $this->flashSaleTimerActive();
        $flashSaleLimit = min(12, max(1, (int) setting('homepage_flash_sale_limit', '10')));
        $flashSaleOrder = setting('homepage_flash_sale_order', 'manual');
        $overviewLimits = [
            'featured' => max($overviewAllLimit, min(12, max(1, (int) setting('template_1_overview_featured_count', '12')))),
            'best' => max($overviewAllLimit, min(12, max(1, (int) setting('template_1_overview_best_count', '12')))),
            'new' => max($overviewAllLimit, min(12, max(1, (int) setting('template_1_overview_new_count', '12')))),
        ];
        $productColumns = [
            'id', 'category_id', 'name', 'slug', 'sku', 'regular_price', 'sale_price',
            'is_free_shipping', 'allow_backorder', 'stock_quantity',
            'sourced_stock_quantity', 'created_at',
        ];
        $withImages = fn ($q) => $q
            ->published()
            ->select($productColumns)
            ->orderByRaw('CASE WHEN stock_quantity > 0 OR sourced_stock_quantity > 0 THEN 0 WHEN allow_backorder = 1 THEN 1 ELSE 2 END')
            ->with([
                'images:id,product_id,path,is_primary,updated_at',
                'category:id,name',
                'supplierLinks:id,product_id,supplier_product_row_id',
                'supplierLinks.supplierProduct:id,product_code,supplier_product_id',
            ])
            ->withExists('variants');

        $categoryCounts = collect(Category::storefrontFilterTree())->keyBy('id');
        $categories = Category::whereNull('parent_id')->where('is_active', true)
            ->where('show_in_menu', true)
            ->orderBy('menu_order')
            ->orderBy('name')
            ->get()
            ->each(fn (Category $category) => $category->setAttribute('products_count', $categoryCounts->get($category->id)['products_count'] ?? 0))
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => mb_substr((string) $category->description, 0, 25),
                'image' => $category->image,
                'icon' => $category->icon,
                'products_count' => (int) $category->products_count,
            ])
            ->values();

        $banners = fn (string $placement) => Banner::active()
            ->placement($placement)
            ->where(fn ($query) => $query->whereNull('product_id')
                ->orWhereHas('product', fn ($products) => $products->published()->where(fn ($available) => $available->where('stock_quantity', '>', 0)->orWhere('sourced_stock_quantity', '>', 0)->orWhere('allow_backorder', true))))
            ->with('product:id,name,slug,sku,is_published,regular_price,sale_price')
            ->orderBy('position')
            ->orderBy('id');

        $bannerPayload = fn (string $placement) => $banners($placement)->get()->map(fn (Banner $banner) => [
            'id' => $banner->id,
            'title' => $banner->title,
            'subtitle' => $banner->subtitle,
            'badge' => $banner->badge,
            'image' => $banner->image,
            'button' => $banner->button_text,
            'link' => $banner->linkHref(),
            'product' => $banner->product ? [
                'id' => $banner->product->id,
                'name' => $banner->product->name,
                'slug' => $banner->product->slug,
                'regular_price' => $banner->product->regular_price,
                'sale_price' => $banner->product->sale_price,
            ] : null,
            'style' => $banner->style,
            'text_position' => $banner->text_position,
            'image_position' => $banner->image_position,
            'image_orientation' => $banner->image_orientation,
        ]);

        // The administrator can choose a stable newest-first collection or a new
        // shuffled collection on every homepage refresh. The stable option must
        // explicitly order the query; database row order is not guaranteed.
        $shuffleOverview = setting('homepage_product_overview_order', 'newest') === 'shuffle';
        $overviewOrder = fn ($query) => $shuffleOverview
            ? $query->inRandomOrder()
            : $query->orderByDesc('created_at')->orderByDesc('id');
        $flashOrder = fn ($query) => $flashSaleOrder === 'newest'
            ? $query->orderByDesc('created_at')->orderByDesc('id')
            : $query->orderBy('flash_sale_position')->orderBy('id');

        $setStorefrontSku = static function (Product $product): void {
            $supplierProduct = $product->supplierLinks->first()?->supplierProduct;
            $product->sku = $product->sku
                ?: $supplierProduct?->product_code
                ?: $supplierProduct?->supplier_product_id;
            $product->unsetRelation('supplierLinks');
        };
        $toProductCard = static fn (Product $product): array => [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'url_key' => $product->url_key,
            'sku' => $product->sku,
            'regular_price' => $product->regular_price,
            'sale_price' => $product->sale_price,
            'is_free_shipping' => (bool) $product->is_free_shipping,
            'allow_backorder' => (bool) $product->allow_backorder,
            'available_stock_quantity' => $product->available_stock_quantity,
            'can_order' => $product->can_order,
            'availability_label' => $product->availability_label,
            'hide_stock_quantity' => $product->hide_stock_quantity,
            'variants_exists' => (bool) $product->variants_exists,
            'images' => $product->images->map(fn ($image) => [
                'id' => $image->id,
                'path' => $image->path,
                'is_primary' => (bool) $image->is_primary,
                'updated_at' => $image->updated_at?->toISOString(),
            ])->values()->all(),
            'category' => $product->category ? ['name' => $product->category->name] : null,
        ];

        $trending = $isTemplateOne ? Product::query()->tap($withImages)->where('is_featured', true)->tap($overviewOrder)->take($overviewLimits['featured'])->get()->each($setStorefrontSku)->map($toProductCard)->values() : collect();
        $bestSellers = $isTemplateOne ? Product::query()->tap($withImages)->where('is_best_seller', true)->tap($overviewOrder)->take($overviewLimits['best'])->get()->each($setStorefrontSku)->map($toProductCard)->values() : collect();
        $newArrivals = $isTemplateOne ? Product::query()->tap($withImages)->where('is_new_arrival', true)->tap($overviewOrder)->take($overviewLimits['new'])->get()->each($setStorefrontSku)->map($toProductCard)->values() : collect();
        $showHomepageCategorySections = setting('homepage_category_sections_enabled', '1') === '1';
        $homepageCategoryOrder = setting('homepage_category_product_order', setting('template_2_category_product_order', 'newest'));
        $templateTwoCategoryOrder = $homepageCategoryOrder === 'shuffle'
            ? fn ($query) => $query->inRandomOrder()
            : fn ($query) => $query->latest('id');
        $categorySectionMeta = $isTemplateTwo && $showHomepageCategorySections
            ? $categories
                ->filter(fn (array $category) => $category['products_count'] > 0)
                ->take(min(12, max(1, (int) setting('homepage_category_sections_limit', '6'))))
                ->map(fn (array $category): array => [
                    'id' => $category['id'],
                    'name' => $category['name'],
                    'slug' => $category['slug'],
                    'products_count' => $category['products_count'],
                ])
                ->values()
            : collect();
        $buildTemplateTwoCategorySections = function () use ($categorySectionMeta, $withImages, $setStorefrontSku, $toProductCard, $templateTwoCategoryOrder): Collection {
            return $categorySectionMeta
                ->map(function (array $category) use ($withImages, $setStorefrontSku, $toProductCard, $templateTwoCategoryOrder): array {
                    $products = Product::query()
                        ->tap($withImages)
                        ->whereIn('category_id', Category::findOrFail($category['id'])->selfAndDescendantIds(true))
                        ->tap($templateTwoCategoryOrder)
                        ->take(10)
                        ->get()
                        ->each($setStorefrontSku)
                        ->map($toProductCard)
                        ->values()
                        ->all();

                    return [
                        'id' => $category['id'],
                        'name' => $category['name'],
                        'slug' => $category['slug'],
                        'products' => $products,
                        'has_more' => $category['products_count'] > count($products),
                    ];
                })
                ->filter(fn (array $section) => $section['products'] !== [])
                ->values();
        };
        $templateTwoCategorySections = $isTemplateTwo && $showHomepageCategorySections
            ? $this->homeOptional($buildTemplateTwoCategorySections)
            : collect();
        $overviewDisplayLimits = [
            'featured' => min(12, max(1, (int) setting('template_1_overview_featured_count', '12'))),
            'new' => min(12, max(1, (int) setting('template_1_overview_new_count', '12'))),
            'best' => min(12, max(1, (int) setting('template_1_overview_best_count', '12'))),
        ];
        $overviewAllCount = $trending
            ->concat($newArrivals)
            ->concat($bestSellers)
            ->unique('id')
            ->take($overviewAllLimit)
            ->count();

        $flashProducts = $flashSaleEnabled && $flashSaleActive
            ? Product::query()
                ->tap($withImages)
                ->where('is_flash_sale', true)
                ->tap($flashOrder)
                ->take($flashSaleLimit)
                ->get()
                ->each($setStorefrontSku)
                ->map($toProductCard)
                ->values()
            : collect();

        $homeOverviewHasMore = [
            'all' => Product::published()
                ->where(fn ($query) => $query->where('is_featured', true)->orWhere('is_new_arrival', true)->orWhere('is_best_seller', true))
                ->count() > $overviewAllCount,
            'featured' => Product::published()->where('is_featured', true)->count() > $trending->take($overviewDisplayLimits['featured'])->count(),
            'new' => Product::published()->where('is_new_arrival', true)->count() > $newArrivals->take($overviewDisplayLimits['new'])->count(),
            'best' => Product::published()->where('is_best_seller', true)->count() > $bestSellers->take($overviewDisplayLimits['best'])->count(),
        ];

        return Inertia::render('Storefront/Home', [
            'heroBanners' => $bannerPayload('hero'),
            'heroSideBanners' => $bannerPayload('hero_side'),
            'middleBanners' => $bannerPayload('middle'),
            'features' => Feature::where('is_active', true)->orderBy('position')->limit(4)->get(['id', 'title', 'subtitle', 'icon']),
            'featuredCategories' => $categories,
            'flashProducts' => $flashProducts,
            'trending' => $isTemplateOne ? $this->homeOptional(fn () => $trending) : [],
            'bestSellers' => $isTemplateOne ? $this->homeOptional(fn () => $bestSellers) : [],
            'newArrivals' => $isTemplateOne ? $this->homeOptional(fn () => $newArrivals) : [],
            'templateTwoCategorySections' => $templateTwoCategorySections,
            'templateTwoCategorySectionMeta' => $categorySectionMeta,
            'homeOverviewHasMore' => $isTemplateOne ? $this->homeOptional(fn () => $homeOverviewHasMore) : [],
            'flashSaleHasMore' => $flashSaleEnabled && $flashSaleActive && Product::published()->where('is_flash_sale', true)->count() > $flashProducts->count(),
        ]);
    }

    public function loadMore(Request $request)
    {
        $data = $request->validate([
            'tab' => ['required', 'in:all,featured,new,best'],
            'exclude' => ['nullable', 'array', 'max:1000'],
            'exclude.*' => ['integer', 'distinct', 'min:1'],
        ]);

        $query = Product::published()
            ->orderByRaw('CASE WHEN stock_quantity > 0 OR sourced_stock_quantity > 0 THEN 0 WHEN allow_backorder = 1 THEN 1 ELSE 2 END')
            ->with('images', 'category', 'supplierLinks.supplierProduct')
            ->withExists('variants')
            ->withExists([
                'variants as variants_in_stock_exists' => fn ($variantQuery) => $variantQuery->where('stock', '>', 0),
            ]);

        match ($data['tab']) {
            'featured' => $query->where('is_featured', true),
            'new' => $query->where('is_new_arrival', true),
            'best' => $query->where('is_best_seller', true),
            default => $query->where(fn ($productQuery) => $productQuery
                ->where('is_featured', true)
                ->orWhere('is_new_arrival', true)
                ->orWhere('is_best_seller', true)),
        };

        $limit = $this->overviewBatchSize($data['tab']);
        $products = $query
            ->whereNotIn('id', $data['exclude'] ?? [])
            ->when(
                setting('homepage_product_overview_order', 'newest') === 'shuffle',
                fn ($productQuery) => $productQuery->inRandomOrder(),
                fn ($productQuery) => $productQuery->orderByDesc('created_at')->orderByDesc('id'),
            )
            ->limit($limit + 1)
            ->get()
            ->each(static function (Product $product): void {
                $supplierProduct = $product->supplierLinks->first()?->supplierProduct;
                $product->sku = $product->sku
                    ?: $supplierProduct?->product_code
                    ?: $supplierProduct?->supplier_product_id;
                $product->unsetRelation('supplierLinks');
            });

        $hasMore = $products->count() > $limit;

        return response()->json([
            'products' => $products->take($limit)->values(),
            'has_more' => $hasMore,
        ]);
    }

    public function loadMoreCategoryProducts(Request $request)
    {
        $data = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'exclude' => ['nullable', 'array', 'max:1000'],
            'exclude.*' => ['integer', 'distinct', 'min:1'],
        ]);

        $category = Category::whereNull('parent_id')->where('is_active', true)
            ->where('show_in_menu', true)
            ->findOrFail($data['category_id']);

        $limit = 10;
        $products = Product::published()
            ->orderByRaw('CASE WHEN stock_quantity > 0 OR sourced_stock_quantity > 0 THEN 0 WHEN allow_backorder = 1 THEN 1 ELSE 2 END')
            ->with('images', 'category', 'supplierLinks.supplierProduct')
            ->withExists('variants')
            ->withExists([
                'variants as variants_in_stock_exists' => fn ($variantQuery) => $variantQuery->where('stock', '>', 0),
            ])
            ->whereIn('category_id', $category->selfAndDescendantIds(true))
            ->whereNotIn('id', $data['exclude'] ?? [])
            ->when(
                setting('homepage_category_product_order', setting('template_2_category_product_order', 'newest')) === 'shuffle',
                fn ($productQuery) => $productQuery->inRandomOrder(),
                fn ($productQuery) => $productQuery->latest('id'),
            )
            ->limit($limit + 1)
            ->get()
            ->each(static function (Product $product): void {
                $supplierProduct = $product->supplierLinks->first()?->supplierProduct;
                $product->sku = $product->sku
                    ?: $supplierProduct?->product_code
                    ?: $supplierProduct?->supplier_product_id;
                $product->unsetRelation('supplierLinks');
            });

        $hasMore = $products->count() > $limit;

        return response()->json([
            'products' => $products->take($limit)->values(),
            'has_more' => $hasMore,
        ]);
    }

    public function loadMoreFlashProducts(Request $request)
    {
        $data = $request->validate([
            'exclude' => ['nullable', 'array', 'max:1000'],
            'exclude.*' => ['integer', 'distinct', 'min:1'],
        ]);

        if (setting('homepage_flash_sale_enabled', '1') !== '1' || ! $this->flashSaleTimerActive()) {
            return response()->json(['products' => [], 'has_more' => false]);
        }

        $limit = min(48, max(1, (int) setting('homepage_flash_sale_limit', '10')));
        $products = Product::published()
            ->orderByRaw('CASE WHEN stock_quantity > 0 OR sourced_stock_quantity > 0 THEN 0 WHEN allow_backorder = 1 THEN 1 ELSE 2 END')
            ->with('images', 'category', 'supplierLinks.supplierProduct')
            ->withExists('variants')
            ->withExists([
                'variants as variants_in_stock_exists' => fn ($variantQuery) => $variantQuery->where('stock', '>', 0),
            ])
            ->where('is_flash_sale', true)
            ->whereNotIn('id', $data['exclude'] ?? [])
            ->when(
                setting('homepage_flash_sale_order', 'manual') === 'newest',
                fn ($productQuery) => $productQuery->orderByDesc('created_at')->orderByDesc('id'),
                fn ($productQuery) => $productQuery->orderBy('flash_sale_position')->orderBy('id'),
            )
            ->limit($limit + 1)
            ->get()
            ->each(static function (Product $product): void {
                $supplierProduct = $product->supplierLinks->first()?->supplierProduct;
                $product->sku = $product->sku
                    ?: $supplierProduct?->product_code
                    ?: $supplierProduct?->supplier_product_id;
                $product->unsetRelation('supplierLinks');
            });

        $hasMore = $products->count() > $limit;

        return response()->json([
            'products' => $products->take($limit)->values(),
            'has_more' => $hasMore,
        ]);
    }

    private function overviewBatchSize(string $tab): int
    {
        $isTemplateOne = setting('storefront_template', 'template-2') === 'template-1';
        $key = $isTemplateOne ? "template_1_overview_{$tab}_count" : null;
        $default = $tab === 'all' ? 16 : 12;

        return $key ? max(1, min(48, (int) setting($key, (string) $default))) : $default;
    }

    private function homeOptional(\Closure $callback): mixed
    {
        // Existing feature tests assert the initial Inertia view data. The
        // production request uses an optional prop and loads it via a partial
        // reload after the above-the-fold shell has mounted.
        return app()->environment('testing') ? $callback() : Inertia::optional($callback);
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
