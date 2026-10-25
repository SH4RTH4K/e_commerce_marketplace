<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class StorefrontPageCache
{
    public const VERSION_KEY = 'storefront-page-cache:version';

    public const CSRF_PLACEHOLDER = '__STOREFRONT_CSRF_TOKEN__';

    private const PUBLIC_ROUTES = [
        'home', 'home.two', 'home.three', 'shop', 'shop.category',
        'product.show', 'blog', 'blog.detail', 'about', 'terms', 'privacy',
        'refund', 'return-policy', 'shipping', 'page',
    ];

    public function store()
    {
        $store = config('storefront.page_cache.store');

        return $store ? Cache::store($store) : Cache::store();
    }

    public function ttl(): int
    {
        return (int) config('storefront.page_cache.ttl', 300);
    }

    public function key(Request $request): string
    {
        $query = $request->query();
        ksort($query);

        return 'storefront-page-cache:v'.$this->version().':'.sha1(implode('|', [
            $request->getMethod(),
            $request->getPathInfo(),
            http_build_query($query),
            $this->productDependency($request),
        ]));
    }

    public function version(): int
    {
        return max(1, (int) $this->store()->get(self::VERSION_KEY, 1));
    }

    public function invalidate(): void
    {
        try {
            $store = $this->store();

            if (! $store->has(self::VERSION_KEY)) {
                $store->forever(self::VERSION_KEY, 1);
            }

            $store->increment(self::VERSION_KEY);
        } catch (\Throwable) {
            // Cache availability must never block a catalogue write.
        }
    }

    public function isPublicRoute(Request $request): bool
    {
        $routeName = $request->route()?->getName();

        return is_string($routeName) && in_array($routeName, self::PUBLIC_ROUTES, true);
    }

    private function productDependency(Request $request): string
    {
        if ($request->route()?->getName() !== 'product.show') {
            return '';
        }

        $parameter = $request->route('product');
        $product = $parameter instanceof Product
            ? $parameter
            : (new Product)->resolveRouteBinding($parameter);

        if (! $product) {
            return '';
        }

        return implode('|', [
            $product->getKey(),
            (string) $product->updated_at,
            sha1((string) ProductVariant::where('product_id', $product->getKey())
                ->orderBy('id')
                ->get(['id', 'type', 'value', 'price_delta', 'stock', 'image_path', 'updated_at'])
                ->toJson()),
            sha1((string) ProductImage::where('product_id', $product->getKey())
                ->orderBy('id')
                ->get(['id', 'path', 'alt', 'is_primary', 'position', 'updated_at'])
                ->toJson()),
        ]);
    }
}
