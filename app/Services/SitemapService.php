<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class SitemapService
{
    public const TYPES = ['products', 'categories', 'pages', 'blogs'];

    private const CACHE_TTL = 21600;

    public function index(): array
    {
        return array_map(fn (string $type): array => [
            'loc' => route('sitemap.'.$type),
        ], self::TYPES);
    }

    public function urls(string $type): array
    {
        abort_unless(in_array($type, self::TYPES, true), 404);

        return Cache::remember($this->cacheKey($type), self::CACHE_TTL, fn (): array => match ($type) {
            'products' => $this->products(),
            'categories' => $this->categories(),
            'pages' => $this->pages(),
            'blogs' => $this->blogs(),
        });
    }

    public function rebuild(): array
    {
        $this->invalidate();
        $counts = [];

        foreach (self::TYPES as $type) {
            $counts[$type] = count($this->urls($type));
        }

        return $counts;
    }

    public function invalidate(): void
    {
        foreach (self::TYPES as $type) {
            Cache::forget($this->cacheKey($type));
        }

        // Keep clearing the legacy key for callers and deployments that still
        // have the pre-split sitemap cached.
        Cache::forget('sitemap_urls_v2');
    }

    public function visibleCategories(): Collection
    {
        $categories = Category::orderBy('menu_order')->orderBy('name')->get();
        $byId = $categories->keyBy('id');

        return $categories->filter(function (Category $category) use ($byId): bool {
            $seen = [];
            while ($category) {
                if (! $category->is_active || isset($seen[$category->id])) {
                    return false;
                }
                $seen[$category->id] = true;
                if (! $category->parent_id) {
                    return true;
                }
                $category = $byId->get($category->parent_id);
            }

            return false;
        });
    }

    private function cacheKey(string $type): string
    {
        // Guides are code-backed rather than stored in a blog table. Their
        // content fingerprint changes the cache key on the next request after
        // a guide is added or edited, while the nightly rebuild remains the
        // safety net for all sitemap groups.
        if ($type === 'blogs') {
            return 'sitemap_urls_v3_blogs_'.sha1(json_encode(
                app(ShoppingGuideService::class)->links(),
                JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            ));
        }

        return 'sitemap_urls_v3_'.$type;
    }

    private function products(): array
    {
        return Product::published()
            ->orderBy('id')
            ->get(['id', 'slug', 'sku', 'updated_at'])
            ->map(fn (Product $product): array => [
                'loc' => route('product.show', $product),
                'priority' => '0.7',
                'changefreq' => 'weekly',
                'lastmod' => $product->updated_at?->toAtomString(),
            ])
            ->all();
    }

    private function categories(): array
    {
        return $this->visibleCategories()
            ->map(fn (Category $category): array => [
                'loc' => route('shop.category', $category),
                'priority' => '0.8',
                'changefreq' => 'weekly',
                'lastmod' => $category->updated_at?->toAtomString(),
            ])
            ->values()
            ->all();
    }

    private function pages(): array
    {
        $pages = [
            [route('home'), '1.0', 'daily'],
            [route('shop'), '0.9', 'daily'],
        ];

        foreach (['about', 'contact', 'terms', 'privacy', 'refund', 'shipping'] as $page) {
            $pages[] = [route($page), '0.5', 'monthly'];
        }

        return array_map(fn (array $page): array => [
            'loc' => $page[0],
            'priority' => $page[1],
            'changefreq' => $page[2],
        ], $pages);
    }

    private function blogs(): array
    {
        $urls = [[
            'loc' => route('blog'),
            'priority' => '0.5',
            'changefreq' => 'monthly',
        ]];

        foreach (app(ShoppingGuideService::class)->links() as $guide) {
            $urls[] = [
                'loc' => route('blog.detail', $guide['slug']),
                'priority' => '0.5',
                'changefreq' => 'monthly',
            ];
        }

        return $urls;
    }
}
