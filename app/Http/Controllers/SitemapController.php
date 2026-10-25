<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\SitemapService;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(SitemapService $sitemaps)
    {
        return response()
            ->view('sitemap-index', ['sitemaps' => $sitemaps->index()])
            ->header('Content-Type', 'application/xml')
            ->header('Cache-Control', 'public, max-age=21600');
    }

    public function child(string $type, SitemapService $sitemaps)
    {
        return response()
            ->view('sitemap', ['urls' => $sitemaps->urls($type)])
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
        $categories = app(SitemapService::class)->visibleCategories()->where('show_in_menu', true)->take(12);
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
                $lines[] = '- [' . str_replace(['[', ']'], '', $category->name) . '](' . route('shop.category', $category) . ')';
            }
        }

        if ($products->isNotEmpty()) {
            $lines = array_merge($lines, ['', '## Featured Products']);
            foreach ($products as $product) {
                $priceText = ' - ' . money($product->price);
                $lines[] = '- [' . str_replace(['[', ']'], '', $product->name) . '](' . route('product.show', $product) . ')' . $priceText;
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

}
