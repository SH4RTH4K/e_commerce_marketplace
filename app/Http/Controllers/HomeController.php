<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Feature;
use App\Models\Product;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index()
    {
        $isTemplateOne = setting('storefront_template', 'template-2') === 'template-1';
        $overviewAllLimit = $isTemplateOne ? min(48, max(1, (int) setting('template_1_overview_all_count', '16'))) : 12;
        $overviewLimits = [
            'featured' => $isTemplateOne
                ? max($overviewAllLimit, min(48, max(1, (int) setting('template_1_overview_featured_count', '12'))))
                : min(48, max(1, (int) setting('template_2_overview_featured_count', '12'))),
            'best' => $isTemplateOne
                ? max($overviewAllLimit, min(48, max(1, (int) setting('template_1_overview_best_count', '12'))))
                : min(48, max(1, (int) setting('template_2_overview_best_count', '12'))),
            'new' => $isTemplateOne
                ? max($overviewAllLimit, min(48, max(1, (int) setting('template_1_overview_new_count', '12'))))
                : min(48, max(1, (int) setting('template_2_overview_new_count', '12'))),
        ];
        $withImages = fn ($q) => $q
            ->published()
            ->with('images', 'category')
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

        $trending = Product::query()->tap($withImages)->where('is_featured', true)->take($overviewLimits['featured'])->get();
        $bestSellers = Product::query()->tap($withImages)->where('is_best_seller', true)->take($overviewLimits['best'])->get();
        $newArrivals = Product::query()->tap($withImages)->where('is_new_arrival', true)->take($overviewLimits['new'])->get();
        $overviewDisplayLimits = [
            'featured' => $isTemplateOne
                ? min(48, max(1, (int) setting('template_1_overview_featured_count', '12')))
                : $overviewLimits['featured'],
            'new' => $isTemplateOne
                ? min(48, max(1, (int) setting('template_1_overview_new_count', '12')))
                : $overviewLimits['new'],
            'best' => $isTemplateOne
                ? min(48, max(1, (int) setting('template_1_overview_best_count', '12')))
                : $overviewLimits['best'],
        ];
        $overviewAllCount = $trending
            ->concat($newArrivals)
            ->concat($bestSellers)
            ->unique('id')
            ->take($overviewAllLimit)
            ->count();

        return Inertia::render('Storefront/Home', [
            'heroBanners'     => $bannerPayload('hero'),
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
            'flashProducts'   => Product::query()->tap($withImages)->where('is_flash_sale', true)->orderBy('flash_sale_position')->orderBy('id')->get(),
            'trending'        => $trending,
            'bestSellers'     => $bestSellers,
            'newArrivals'     => $newArrivals,
            'homeOverviewHasMore' => [
                'all' => Product::published()
                    ->where(fn ($query) => $query->where('is_featured', true)->orWhere('is_new_arrival', true)->orWhere('is_best_seller', true))
                    ->count() > $overviewAllCount,
                'featured' => Product::published()->where('is_featured', true)->count() > $trending->take($overviewDisplayLimits['featured'])->count(),
                'new' => Product::published()->where('is_new_arrival', true)->count() > $newArrivals->take($overviewDisplayLimits['new'])->count(),
                'best' => Product::published()->where('is_best_seller', true)->count() > $bestSellers->take($overviewDisplayLimits['best'])->count(),
            ],
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
            ->with('images', 'category')
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
            ->orderByDesc('id')
            ->limit($limit + 1)
            ->get();

        $hasMore = $products->count() > $limit;

        return response()->json([
            'products' => $products->take($limit)->values(),
            'has_more' => $hasMore,
        ]);
    }

    private function overviewBatchSize(string $tab): int
    {
        $isTemplateOne = setting('storefront_template', 'template-2') === 'template-1';
        $key = $isTemplateOne
            ? "template_1_overview_{$tab}_count"
            : "template_2_overview_{$tab}_count";
        $default = $tab === 'all' ? 16 : 12;

        return max(1, min(48, (int) setting($key, (string) $default)));
    }
}
