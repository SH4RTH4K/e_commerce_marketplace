<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Services\ShoppingGuideService;
use Inertia\Inertia;

class ShoppingGuideController extends Controller
{
    public function index(ShoppingGuideService $guides)
    {
        return Inertia::render('Storefront/Blog', [
            'guides' => $guides->links(),
            'seo' => ['title' => 'Shopping Guides | '.site_name(), 'description' => 'Practical guides to clothing sizes, gadget specifications, ordering, delivery and returns at '.site_name().'.', 'canonical' => route('blog')],
        ]);
    }

    public function show(string $slug, ShoppingGuideService $guides)
    {
        if (in_array($slug, ['winter-dresses', 'mens-holiday-gifts', 'winter-to-spring-trends'], true)) {
            return redirect()->route('blog', [], 301);
        }
        $guide = $guides->all()[$slug] ?? null;
        abort_unless($guide, 404);
        $url = route('blog.detail', $slug);
        $category = isset($guide['category_slug'])
            ? Category::where('slug', $guide['category_slug'])->where('is_active', true)->first()
            : null;
        $guideProducts = $category ? Product::published()
            ->whereIn('category_id', $category->selfAndDescendantIds(true))
            ->where('stock_quantity', '>', 0)
            ->orderByDesc('stock_quantity')->orderByDesc('id')
            ->take(4)->get()
            ->map(fn (Product $product): array => ['name' => $product->name, 'url' => route('product.show', $product)])
            ->all() : [];

        return Inertia::render('Storefront/BlogDetail', [
            'guide' => $guide, 'guides' => $guides->links(),
            'guideCategory' => $category ? ['name' => $category->name, 'url' => route('shop.category', $category)] : null,
            'guideProducts' => $guideProducts,
            'seo' => ['title' => $guide['title'].' | '.site_name(), 'description' => $guide['summary'], 'canonical' => $url],
            'structuredData' => ['@context' => 'https://schema.org', '@type' => 'BlogPosting',
                'headline' => $guide['title'], 'description' => $guide['summary'], 'url' => $url,
                'mainEntityOfPage' => $url, 'inLanguage' => 'en',
                'author' => ['@type' => 'Organization', 'name' => site_name(), 'url' => route('home')],
                'publisher' => ['@type' => 'Organization', 'name' => site_name(), 'logo' => ['@type' => 'ImageObject', 'url' => logo_url()]],
            ],
        ]);
    }
}
