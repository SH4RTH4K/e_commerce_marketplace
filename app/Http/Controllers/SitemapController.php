<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    public function index()
    {
        // Cache sitemap for 6 hours — avoids DB hit on every crawler request
        $urls = Cache::remember('sitemap_urls', 21600, function () {
            $list = [];
            $list[] = ['loc' => route('home'), 'priority' => '1.0', 'changefreq' => 'daily'];
            $list[] = ['loc' => route('shop'), 'priority' => '0.9', 'changefreq' => 'daily'];

            foreach (Category::where('is_active', true)->get() as $category) {
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
}
