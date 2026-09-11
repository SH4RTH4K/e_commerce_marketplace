<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Feature;
use App\Models\Product;

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
        ]);

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
            'trending'        => Product::query()->tap($withImages)->where('is_featured', true)->take($overviewLimits['featured'])->get(),
            'bestSellers'     => Product::query()->tap($withImages)->where('is_best_seller', true)->take($overviewLimits['best'])->get(),
            'newArrivals'     => Product::query()->tap($withImages)->where('is_new_arrival', true)->take($overviewLimits['new'])->get(),
        ]);
    }
}
