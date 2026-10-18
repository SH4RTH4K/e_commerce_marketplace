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
            'image' => seo_image_url(),
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
            'name' => site_name(), 'url' => route('home'), 'logo' => seo_image_url(),
            'hasMerchantReturnPolicy' => [
                '@type' => 'MerchantReturnPolicy', 'merchantReturnLink' => route('refund'),
            ],
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
            'description' => Str::limit(trim(html_entity_decode(strip_tags((string) ($product->short_description ?: $product->description ?: $product->meta_description)), ENT_QUOTES | ENT_HTML5, 'UTF-8')), 5000, ''),
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
        $service = app(ProductOfferService::class);
        $offerings = [];
        foreach ($service->all($product) as $offering) {
            $offerings[] = $offering;
            if (count($offerings) > 200) break;
        }
        $offer = function (array $offering) use ($product): array {
            $data = [
                '@type' => 'Offer', 'url' => $offering['url'], 'priceCurrency' => 'BDT',
                'price' => number_format($offering['price'], 2, '.', ''),
                'itemCondition' => 'https://schema.org/NewCondition',
                'availability' => 'https://schema.org/'.($offering['in_stock'] ? 'InStock' : 'OutOfStock'),
                'seller' => ['@type' => 'Organization', 'name' => site_name(), 'url' => route('home')],
            ];
            // Only an explicitly free-shipping product has a single country-wide
            // rate. Other rates depend on location/weight and stay in checkout.
            if ($product->is_free_shipping) $data['shippingDetails'] = [
                '@type' => 'OfferShippingDetails',
                'shippingDestination' => ['@type' => 'DefinedRegion', 'addressCountry' => 'BD'],
                'shippingRate' => ['@type' => 'MonetaryAmount', 'value' => '0.00', 'currency' => 'BDT'],
            ];
            return $data;
        };
        $types = array_keys($service->groups($product));
        $supported = ['color', 'size', 'material', 'pattern', 'gender', 'ageGroup'];
        $mapped = array_map(fn ($type) => strtolower($type) === 'agegroup' ? 'ageGroup' : strtolower($type), $types);
        if (count($offerings) > 200) {
            // Keep HTML bounded for unusually large combinations. This range
            // includes all selectable prices, never an unavailable base price.
            $groups = collect($service->groups($product));
            $item['offers'] = ['@type' => 'AggregateOffer', 'priceCurrency' => 'BDT',
                'lowPrice' => number_format(max(0, $product->price + $groups->sum(fn ($options) => collect($options)->min('price_delta'))), 2, '.', ''),
                'highPrice' => number_format(max(0, $product->price + $groups->sum(fn ($options) => collect($options)->max('price_delta'))), 2, '.', ''),
                'offerCount' => $groups->reduce(fn ($count, $options) => $count * count($options), 1)];
        } elseif (count($offerings) > 1 && array_diff($mapped, $supported) === []) {
            $item['@type'] = 'ProductGroup';
            $item['productGroupID'] = (string) $product->id;
            $item['variesBy'] = array_map(fn ($type) => 'https://schema.org/'.$type, $mapped);
            $item['hasVariant'] = array_map(function ($offering) use ($item, $offer) {
                $variant = array_diff_key($item, array_flip(['productGroupID', 'variesBy']));
                $variant['@type'] = 'Product';
                $variant['@id'] = $offering['url'].'#product';
                $variant['url'] = $offering['url'];
                $variant['sku'] = $offering['sku'];
                $variant['name'] .= ' — '.$offering['label'];
                foreach ($offering['selection'] as $type => $value) $variant[strtolower($type) === 'agegroup' ? 'ageGroup' : strtolower($type)] = $value;
                $variant['offers'] = $offer($offering);
                return $variant;
            }, $offerings);
        } else {
            $item['offers'] = count($offerings) === 1 ? $offer($offerings[0]) : array_map($offer, $offerings);
        }
        $reviews = $product->approvedReviews()->reorder()->selectRaw('COUNT(*) AS review_count, AVG(rating) AS average_rating')->first();
        if ((int) $reviews->review_count > 0) $item['aggregateRating'] = [
            '@type' => 'AggregateRating', 'ratingValue' => round((float) $reviews->average_rating, 2),
            'reviewCount' => (int) $reviews->review_count, 'bestRating' => 5, 'worstRating' => 1,
        ];

        return ['@context' => 'https://schema.org', '@graph' => [$item, $this->breadcrumbs($path, $product)]];
    }
}
