<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StorefrontSeoService
{
    public function isPrivate(Request $request): bool
    {
        return $request->is('admin', 'admin/*', 'account', 'account/*', 'cart', 'cart/*',
            'checkout', 'checkout/*', 'order', 'order/*', 'track', 'wishlist', 'login',
            'register', 'forgot-password', 'reset-password', 'verify', 'api/*', 'home/*');
    }

    public function defaults(Request $request): array
    {
        $pages = [
            'about' => ['About Us', 'Learn about '.site_name().' and our online store in Bangladesh. Explore our product collections and ways to contact our team.'],
            'contact' => ['Contact Us', 'Contact '.site_name().' for product questions, order assistance and customer support. Find our current contact details and send a message.'],
            'terms' => ['Terms of Service', 'Read the '.site_name().' terms of service for orders, payments and using our online store.'],
            'privacy' => ['Privacy Policy', 'Read how '.site_name().' handles personal information when you browse, contact us or place an order.'],
            'refund' => ['Refund Policy', 'Read the '.site_name().' refund and return policy, including how to request assistance with an order.'],
            'shipping' => ['Shipping Information', 'Check '.site_name().' delivery information and current shipping charges before placing your order.'],
        ];
        $name = $request->route()?->getName();
        if ($name === 'page') {
            $name = match ($request->route('slug')) {
                'refund', 'refund-policy' => 'refund',
                default => $request->route('slug'),
            };
        }
        $name = $name === 'return-policy' ? 'refund' : $name;
        $seo = [
            'canonical' => isset($pages[$name]) ? route($name) : $request->url(),
            'robots' => $this->isPrivate($request) ? 'noindex, nofollow' : 'index, follow',
            'image' => logo_url(),
        ];
        if (isset($pages[$name])) {
            $seo['title'] = $pages[$name][0].' | '.site_name();
            $seo['description'] = $pages[$name][1];
        }
        if (in_array($name, ['home.two', 'home.three'], true)) {
            $seo['canonical'] = route('home');
        }

        return $seo;
    }

    public function organization(): array
    {
        $organization = [
            '@type' => 'OnlineStore', '@id' => route('home').'#organization',
            'name' => site_name(), 'url' => route('home'), 'logo' => logo_url(),
        ];
        foreach (['telephone' => 'contact_phone', 'email' => 'contact_email'] as $property => $setting) {
            if ($value = trim((string) setting($setting, ''))) {
                $organization[$property] = $value;
            }
        }
        $social = array_values(array_filter(array_map(fn ($key) => trim((string) setting($key, '')),
            ['facebook_url', 'instagram_url', 'youtube_url', 'twitter_url']),
            fn ($url) => filter_var($url, FILTER_VALIDATE_URL) && preg_match('~^https?://~i', $url)));
        if ($social !== []) {
            $organization['sameAs'] = $social;
        }

        return ['@context' => 'https://schema.org', '@graph' => [$organization, [
            '@type' => 'WebSite', '@id' => route('home').'#website',
            'name' => site_name(), 'url' => route('home'),
            'publisher' => ['@id' => $organization['@id']],
        ]]];
    }

    public function breadcrumbs(array $path, ?Product $product = null): array
    {
        $items = [['name' => 'Home', 'item' => route('home')], ['name' => 'Shop', 'item' => route('shop')]];
        foreach ($path as $category) {
            if (isset($category['is_active']) && ! $category['is_active']) {
                break;
            }
            $items[] = ['name' => $category['name'], 'item' => route('shop.category', $category['slug'])];
        }
        if ($product) {
            $items[] = ['name' => $product->name, 'item' => route('product.show', $product)];
        }

        return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(fn ($item, $index) => array_merge([
                '@type' => 'ListItem', 'position' => $index + 1,
            ], $item), $items, array_keys($items))];
    }

    public function product(Product $product, array $path): array
    {
        $url = route('product.show', $product);
        $item = [
            '@type' => 'Product', '@id' => $url.'#product', 'url' => $url,
            'name' => $product->name,
            'description' => Str::limit(trim(strip_tags((string) ($product->meta_description ?: $product->short_description ?: $product->description))), 5000),
        ];
        $images = $product->images->map(fn ($image) => image_url($image->path, $product->slug))->filter()->values()->all();
        if ($images !== []) {
            $item['image'] = $images;
        }
        if ($product->sku) {
            $item['sku'] = $product->sku;
        }
        if ($product->brand) {
            $item['brand'] = ['@type' => 'Brand', 'name' => $product->brand];
        }
        // Selectable price adjustments may make the base price unavailable.
        // Omit an offer rather than advertise an unselectable price.
        $variablePrice = $product->variants->contains(fn ($variant) => (float) $variant->price_delta !== 0.0);
        if (! $variablePrice) {
            $item['offers'] = [
                '@type' => 'Offer', 'url' => $url, 'priceCurrency' => 'BDT',
                'price' => number_format($product->price, 2, '.', ''),
                'availability' => (int) $product->stock_quantity > 0 || $product->variants->contains(fn ($variant) => (int) $variant->stock > 0)
                    ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'seller' => ['@type' => 'Organization', 'name' => site_name(), 'url' => route('home')],
            ];
        }

        return ['@context' => 'https://schema.org', '@graph' => [$item, $this->breadcrumbs($path, $product)]];
    }
}
