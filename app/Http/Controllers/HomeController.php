<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Feature;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Throwable;

class HomeController extends Controller
{
    public function index()
    {
        $isTemplateOne = setting('storefront_template', 'template-2') === 'template-1';
        $isTemplateTwo = setting('storefront_template', 'template-2') === 'template-2';
        $overviewAllLimit = min(48, max(1, (int) setting('template_1_overview_all_count', '16')));
        $flashSaleEnabled = setting('homepage_flash_sale_enabled', '1') === '1';
        $flashSaleActive = $this->flashSaleTimerActive();
        $flashSaleLimit = min(48, max(1, (int) setting('homepage_flash_sale_limit', '10')));
        $flashSaleOrder = setting('homepage_flash_sale_order', 'manual');
        $overviewLimits = [
            'featured' => max($overviewAllLimit, min(48, max(1, (int) setting('template_1_overview_featured_count', '12')))),
            'best' => max($overviewAllLimit, min(48, max(1, (int) setting('template_1_overview_best_count', '12')))),
            'new' => max($overviewAllLimit, min(48, max(1, (int) setting('template_1_overview_new_count', '12')))),
        ];
        $withImages = fn ($q) => $q
            ->published()
            ->with('images', 'category', 'supplierLinks.supplierProduct')
            ->withExists('variants')
            ->withExists([
                'variants as variants_in_stock_exists' => fn ($variantQuery) => $variantQuery->where('stock', '>', 0),
            ]);

        $categories = Category::where('is_active', true)
            ->where('show_in_menu', true)
            ->withCount(['products' => fn ($q) => $q->published()])
            ->orderBy('menu_order')
            ->orderBy('name')
            ->get();

        $banners = fn (string $placement) => Banner::active()
            ->placement($placement)
            ->with('product:id,name,slug,is_published')
            ->orderBy('position')
            ->orderBy('id');

        $bannerPayload = fn (string $placement) => $banners($placement)->get()->map(fn (Banner $banner) => [
            'id' => $banner->id,
            'title' => $banner->title,
            'subtitle' => $banner->subtitle,
            'badge' => $banner->badge,
            'image' => $banner->image,
            'button' => $banner->button_text,
            'button_text' => $banner->button_text,
            'link' => $banner->linkHref(),
            'link_url' => $banner->link_url,
            'product' => $banner->product ? [
                'id' => $banner->product->id,
                'name' => $banner->product->name,
                'slug' => $banner->product->slug,
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
            $product->sku = $supplierProduct?->product_code
                ?: $product->sku
                ?: $supplierProduct?->supplier_product_id;
            $product->unsetRelation('supplierLinks');
        };

        $trending = $isTemplateOne ? Product::query()->tap($withImages)->where('is_featured', true)->tap($overviewOrder)->take($overviewLimits['featured'])->get()->each($setStorefrontSku) : collect();
        $bestSellers = $isTemplateOne ? Product::query()->tap($withImages)->where('is_best_seller', true)->tap($overviewOrder)->take($overviewLimits['best'])->get()->each($setStorefrontSku) : collect();
        $newArrivals = $isTemplateOne ? Product::query()->tap($withImages)->where('is_new_arrival', true)->tap($overviewOrder)->take($overviewLimits['new'])->get()->each($setStorefrontSku) : collect();
        $showHomepageCategorySections = setting('homepage_category_sections_enabled', '1') === '1';
        $homepageCategoryOrder = setting('homepage_category_product_order', setting('template_2_category_product_order', 'newest'));
        $templateTwoCategoryOrder = $homepageCategoryOrder === 'shuffle'
            ? fn ($query) => $query->inRandomOrder()
            : fn ($query) => $query->latest('id');
        $templateTwoCategorySections = $isTemplateTwo && $showHomepageCategorySections
            ? $categories
                ->filter(fn (Category $category) => $category->products_count > 0)
                ->take(12)
                ->map(function (Category $category) use ($withImages, $setStorefrontSku, $templateTwoCategoryOrder): array {
                    $products = Product::query()
                        ->tap($withImages)
                        ->where('category_id', $category->getKey())
                        ->tap($templateTwoCategoryOrder)
                        ->take(10)
                        ->get()
                        ->each($setStorefrontSku);

                    return [
                        'id' => $category->getKey(),
                        'name' => $category->name,
                        'slug' => $category->slug,
                        'products' => $products,
                        'has_more' => $category->products_count > $products->count(),
                    ];
                })
                ->filter(fn (array $section) => $section['products']->isNotEmpty())
                ->values()
            : collect();
        $overviewDisplayLimits = [
            'featured' => min(48, max(1, (int) setting('template_1_overview_featured_count', '12'))),
            'new' => min(48, max(1, (int) setting('template_1_overview_new_count', '12'))),
            'best' => min(48, max(1, (int) setting('template_1_overview_best_count', '12'))),
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
            : collect();

        return Inertia::render('Storefront/Home', [
            'heroBanners'     => $bannerPayload('hero'),
            'heroSideBanners' => $bannerPayload('hero_side'),
            'middleBanners'   => $bannerPayload('middle'),
            'features'        => Feature::where('is_active', true)->orderBy('position')->get(),
            'featuredCategories' => $categories,
            'coupons'         => Coupon::query()
                ->where('is_active', true)
                ->orderByDesc('created_at')
                ->get()
                ->filter(fn (Coupon $c) => $c->isCurrentlyActive())
                ->values()
                ->take(4),
            'flashProducts'   => $flashProducts,
            'trending'        => $trending,
            'bestSellers'     => $bestSellers,
            'newArrivals'     => $newArrivals,
            'templateTwoCategorySections' => $templateTwoCategorySections,
            'homeOverviewHasMore' => [
                'all' => Product::published()
                    ->where(fn ($query) => $query->where('is_featured', true)->orWhere('is_new_arrival', true)->orWhere('is_best_seller', true))
                    ->count() > $overviewAllCount,
                'featured' => Product::published()->where('is_featured', true)->count() > $trending->take($overviewDisplayLimits['featured'])->count(),
                'new' => Product::published()->where('is_new_arrival', true)->count() > $newArrivals->take($overviewDisplayLimits['new'])->count(),
                'best' => Product::published()->where('is_best_seller', true)->count() > $bestSellers->take($overviewDisplayLimits['best'])->count(),
            ],
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
                $product->sku = $supplierProduct?->product_code
                    ?: $product->sku
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

        $category = Category::where('is_active', true)
            ->where('show_in_menu', true)
            ->findOrFail($data['category_id']);

        $limit = 10;
        $products = Product::published()
            ->with('images', 'category', 'supplierLinks.supplierProduct')
            ->withExists('variants')
            ->withExists([
                'variants as variants_in_stock_exists' => fn ($variantQuery) => $variantQuery->where('stock', '>', 0),
            ])
            ->where('category_id', $category->getKey())
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
                $product->sku = $supplierProduct?->product_code
                    ?: $product->sku
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
                $product->sku = $supplierProduct?->product_code
                    ?: $product->sku
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
