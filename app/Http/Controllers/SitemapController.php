<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    public function index()
    {
        // Cache sitemap for 6 hours — avoids DB hit on every crawler request
        $urls = Cache::remember('sitemap_urls_v2', 21600, function () {
            $list = [];
            $list[] = ['loc' => route('home'), 'priority' => '1.0', 'changefreq' => 'daily'];
            $list[] = ['loc' => route('shop'), 'priority' => '0.9', 'changefreq' => 'daily'];

            foreach (['about', 'contact', 'terms', 'privacy', 'refund', 'shipping'] as $page) {
                $list[] = ['loc' => route($page), 'priority' => '0.5', 'changefreq' => 'monthly'];
            }

            foreach ($this->visibleCategories() as $category) {
                $list[] = [
                    'loc'        => route('shop.category', $category),
                    'priority'   => '0.8',
                    'changefreq' => 'weekly',
                    'lastmod'    => $category->updated_at?->toAtomString(),
                ];
            }

            foreach (Product::published()->get() as $product) {
                $list[] = [
                    'loc'        => route('product.show', $product),
                    'priority'   => '0.7',
                    'changefreq' => 'weekly',
                    'lastmod'    => $product->updated_at?->toAtomString(),
                ];
            }

            return $list;
        });

        return response()
            ->view('sitemap', compact('urls'))
            ->header('Content-Type', 'application/xml')
            ->header('Cache-Control', 'public, max-age=21600');
    }

    public function robots()
    {
        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin',
            'Disallow: /admin/',
            'Disallow: /cart',
            'Disallow: /checkout',
            'Disallow: /account',
            'Disallow: /api/',
            '',
            'Sitemap: ' . route('sitemap'),
        ];

        return response(implode("\n", $lines), 200)
            ->header('Content-Type', 'text/plain')
            ->header('Cache-Control', 'public, max-age=86400');
    }

    public function llms()
    {
        if (setting('seo_llms_txt_enabled', '1') !== '1') {
            abort(404);
        }

        $siteName = site_name();
        $description = trim((string) setting('default_meta_description', ''))
            ?: trim((string) setting('tagline', ''))
            ?: trim((string) setting('footer_text', ''))
            ?: "{$siteName} online store.";
        $notes = trim((string) setting('seo_llms_txt_notes', ''));
        $categories = $this->visibleCategories()->where('show_in_menu', true)->take(12);
        $products = Product::query()
            ->published()
            ->orderByDesc('is_featured')
            ->orderByDesc('created_at')
            ->limit(12)
            ->get(['id', 'name', 'slug', 'sku', 'regular_price', 'sale_price', 'updated_at']);

        $lines = [
            "# {$siteName}",
            '',
            '> ' . strip_tags($description),
            '',
            'Canonical: ' . route('home'),
            'Sitemap: ' . route('sitemap'),
            'Robots: ' . route('robots'),
            '',
            '## Important Pages',
            '- Home: ' . route('home'),
            '- Shop: ' . route('shop'),
            '- Track order: ' . route('track'),
            '- Contact: ' . route('contact'),
        ];

        if ($notes !== '') {
            $lines = array_merge($lines, [
                '',
                '## AI Crawler Notes',
                strip_tags($notes),
            ]);
        }

        if ($categories->isNotEmpty()) {
            $lines = array_merge($lines, ['', '## Main Categories']);
            foreach ($categories as $category) {
                $lines[] = '- ' . $category->name . ': ' . route('shop.category', $category);
            }
        }

        if ($products->isNotEmpty()) {
            $lines = array_merge($lines, ['', '## Featured Products']);
            foreach ($products as $product) {
                $priceText = ' - ' . money($product->price);
                $lines[] = '- ' . $product->name . $priceText . ': ' . route('product.show', $product);
            }
        }

        $lines = array_merge($lines, [
            '',
            '## Guidance',
            '- Use the canonical URLs above for references.',
            '- Product prices, stock, and availability can change; visit the product page for the latest details.',
            '- Do not crawl admin, cart, checkout, or account pages.',
        ]);

        return response(implode("\n", $lines), Response::HTTP_OK)
            ->header('Content-Type', 'text/plain; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=86400');
    }

    private function visibleCategories(): \Illuminate\Support\Collection
    {
        $categories = Category::orderBy('menu_order')->orderBy('name')->get();
        $byId = $categories->keyBy('id');

        return $categories->filter(function (Category $category) use ($byId) {
            $seen = [];
            while ($category) {
                if (! $category->is_active || isset($seen[$category->id])) return false;
                $seen[$category->id] = true;
                if (! $category->parent_id) return true;
                $category = $byId->get($category->parent_id);
            }
            return false;
        });
    }
}
