<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Session-backed shopping cart. The session only stores product id, quantity
 * and the chosen variant label — all prices/names/images are resolved from the
 * database at read time so totals are always server-authoritative.
 */
class CartService
{
    private const KEY = 'cart';

    private ?Collection $cachedItems = null;

    private function raw(): array
    {
        return session(self::KEY, []);
    }

    private function persist(array $lines): void
    {
        $this->cachedItems = null;
        session([self::KEY => $lines]);
    }

    private function lineKey(int $productId, ?string $variant): string
    {
        return $productId . '|' . ($variant ?? '');
    }

    public function add(int $productId, int $qty = 1, ?string $variant = null): void
    {
        $qty   = max(1, $qty);
        $lines = $this->raw();
        $key   = $this->lineKey($productId, $variant);

        if (isset($lines[$key])) {
            $lines[$key]['qty'] += $qty;
        } else {
            $lines[$key] = ['product_id' => $productId, 'qty' => $qty, 'variant' => $variant];
        }

        $this->persist($lines);
    }

    public function update(string $key, int $qty): void
    {
        $lines = $this->raw();
        if (! isset($lines[$key])) {
            return;
        }
        if ($qty <= 0) {
            unset($lines[$key]);
        } else {
            $lines[$key]['qty'] = $qty;
        }
        $this->persist($lines);
    }

    public function remove(string $key): void
    {
        $lines = $this->raw();
        unset($lines[$key]);
        $this->persist($lines);
    }

    public function clear(): void
    {
        $this->cachedItems = null;
        session()->forget(self::KEY);
    }

    /**
     * Resolve the cart into a collection of display-ready line items.
     * Silently drops lines whose product was deleted / unpublished.
     */
    public function items(): Collection
    {
        if ($this->cachedItems !== null) {
            return $this->cachedItems;
        }

        $lines = $this->raw();
        if (empty($lines)) {
            return $this->cachedItems = collect();
        }

        $products = Product::with(['images', 'variants'])
            ->whereIn('id', collect($lines)->pluck('product_id'))
            ->get()
            ->keyBy('id');

        return $this->cachedItems = collect($lines)->map(function ($line, $key) use ($products) {
            $product = $products->get($line['product_id']);
            if (! $product || ! $product->is_published) {
                return null;
            }

            $variant = $line['variant'] ?? null;
            $price = $product->unitPriceForVariant(is_string($variant) ? $variant : null);

            return (object) [
                'key'        => $key,
                'product'    => $product,
                'product_id' => $product->id,
                'name'       => $product->name,
                'slug'       => $product->url_key,
                // Keep the original reference in the cart payload.  The Inertia
                // storefront resolves it when rendering; passing imageUrl() here
                // made externally hosted images go through the image proxy twice.
                'image'      => $product->primaryImage()?->path,
                'variant'    => $variant,
                'price'      => $price,
                'unit_price' => $price,
                'qty'        => (int) $line['qty'],
                'line_total' => $price * (int) $line['qty'],
            ];
        })->filter()->values();
    }

    public function count(): int
    {
        return (int) $this->items()->sum('qty');
    }

    public function qtyInCart(int $productId, ?string $variant = null): int
    {
        $key = $this->lineKey($productId, $variant);
        $line = $this->raw()[$key] ?? null;

        return $line ? (int) $line['qty'] : 0;
    }

    public function subtotal(): float
    {
        return (float) $this->items()->sum('line_total');
    }

    public function toArray(): array
    {
        return [
            'count'    => $this->count(),
            'subtotal' => $this->subtotal(),
        ];
    }
}
