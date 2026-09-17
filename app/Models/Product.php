<?php

namespace App\Models;

use App\Support\ProductSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class Product extends Model
{
    protected static function booted(): void
    {
        static::saving(function (Product $product) {
            if (! $product->exists || $product->isDirty('slug')) {
                $product->slug = ProductSlug::unique(
                    $product->slug ?: $product->name,
                    $product->exists ? (int) $product->getKey() : null,
                );
            }

            // Reserve the previous URL before changing it. Every alias resolves
            // directly to this product's current slug, without redirect chains.
            if ($product->exists && $product->isDirty('slug') && $product->getRawOriginal('slug')) {
                DB::table('product_slug_aliases')->insertOrIgnore([
                    'product_id' => $product->getKey(),
                    'slug' => $product->getRawOriginal('slug'),
                ]);
            }
        });

        static::saved(function (Product $product) {
            if ($product->wasRecentlyCreated || $product->wasChanged(['slug', 'is_published'])) {
                Cache::forget('sitemap_urls');
            }
        });
        static::deleted(fn () => Cache::forget('sitemap_urls'));
    }

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'sku',
        'brand',
        'short_description',
        'description',
        'regular_price',
        'sale_price',
        'stock_quantity',
        'unit',
        'is_published',
        'is_featured',
        'is_new_arrival',
        'is_best_seller',
        'is_flash_sale',
        'flash_sale_position',
        'is_free_shipping',
        'rating',
        'reviews_count',
        'specifications',
        'meta_title',
        'meta_description',
        'meta_keywords',
    ];

    protected $casts = [
        'regular_price'    => 'decimal:2',
        'sale_price'       => 'decimal:2',
        'rating'           => 'decimal:2',
        'is_published'     => 'boolean',
        'is_featured'      => 'boolean',
        'is_new_arrival'   => 'boolean',
        'is_best_seller'   => 'boolean',
        'is_flash_sale'    => 'boolean',
        'is_free_shipping' => 'boolean',
        'specifications'   => 'array',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderByDesc('is_primary')->orderBy('position');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('position');
    }

    public function supplierLinks(): HasMany
    {
        return $this->hasMany(DropshipProductLink::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class)->latest();
    }

    public function approvedReviews(): HasMany
    {
        return $this->reviews()->approved();
    }

    /** Recompute rating + reviews_count from approved customer reviews. */
    public function recalculateRatingFromReviews(): void
    {
        $approved = $this->reviews()->approved();
        $count = (clone $approved)->count();
        $avg = $count > 0 ? round((float) (clone $approved)->avg('rating'), 2) : 0;

        $this->forceFill([
            'rating'         => $avg,
            'reviews_count'  => $count,
        ])->saveQuietly();
    }

    /* --------- scopes --------- */
    public function scopePublished(Builder $q): Builder
    {
        return $q->where('is_published', true);
    }

    /* --------- pricing helpers --------- */
    public function getPriceAttribute(): float
    {
        return (float) ($this->sale_price ?? $this->regular_price);
    }

    public function getOnSaleAttribute(): bool
    {
        return $this->sale_price !== null && (float) $this->sale_price < (float) $this->regular_price;
    }

    public function getDiscountPercentAttribute(): int
    {
        if (! $this->on_sale || (float) $this->regular_price <= 0) {
            return 0;
        }
        return (int) round(100 - ($this->price / (float) $this->regular_price * 100));
    }


    /**
     * Parse a cart/storefront variant label ("Size: M, Color: Black" or "Red, XL") into type => value or indexed values.
     *
     * @return array<string, string>
     */
    public static function parseVariantSelection(?string $label): array
    {
        if ($label === null || trim($label) === '') {
            return [];
        }

        $pairs = [];
        foreach (preg_split('/\s*,\s*/', $label) ?: [] as $part) {
            if (str_contains($part, ':')) {
                [$type, $value] = array_map('trim', explode(':', $part, 2));
                if ($type !== '' && $value !== '') {
                    $pairs[$type] = $value;
                }
            } else {
                $trimmed = trim($part);
                if ($trimmed !== '') {
                    $pairs['__val_' . count($pairs)] = $trimmed;
                }
            }
        }

        return $pairs;
    }

    /** Sum of price_delta for selected options (server-authoritative). */
    public function variantPriceAdjustment(?string $variantLabel): float
    {
        if ($variantLabel === null || trim($variantLabel) === '') {
            return 0.0;
        }

        $pairs = self::parseVariantSelection($variantLabel);
        if ($pairs === []) {
            return 0.0;
        }

        $variants = $this->relationLoaded('variants')
            ? $this->variants
            : $this->variants()->get();

        if ($variants->isEmpty()) {
            return 0.0;
        }

        $delta = 0.0;
        foreach ($pairs as $type => $value) {
            if (str_starts_with($type, '__val_')) {
                $match = $variants->first(
                    fn (ProductVariant $v) => strcasecmp((string) $v->value, $value) === 0
                );
            } else {
                $match = $variants->first(
                    fn (ProductVariant $v) => strcasecmp((string) $v->type, $type) === 0
                        && strcasecmp((string) $v->value, $value) === 0
                );
                if (! $match) {
                    // Fallback to match just by value
                    $match = $variants->first(
                        fn (ProductVariant $v) => strcasecmp((string) $v->value, $value) === 0
                    );
                }
            }
            if ($match) {
                $delta += (float) $match->price_delta;
            }
        }

        return $delta;
    }

    /** Selling unit price for a selected variant combination. */
    public function unitPriceForVariant(?string $variantLabel = null): float
    {
        return max(0, (float) $this->price + $this->variantPriceAdjustment($variantLabel));
    }

    /** Compare-at (regular) price with the same variant adjustments, when on sale. */
    public function compareAtPriceForVariant(?string $variantLabel = null): ?float
    {
        if (! $this->on_sale) {
            return null;
        }

        return max(0, (float) $this->regular_price + $this->variantPriceAdjustment($variantLabel));
    }

    /* --------- image helpers --------- */
    public function primaryImage(): ?ProductImage
    {
        return $this->images->firstWhere('is_primary', true) ?? $this->images->first();
    }

    public function imageUrl(): string
    {
        return image_url($this->primaryImage()?->path, $this->slug);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Resolve route binding accepting both slug (storefront) and numeric ID (admin).
     */
    public function resolveRouteBinding($value, $field = null)
    {
        if ($field !== null) {
            return $this->where($field, $value)->first();
        }

        return $this->where('slug', $value)->first()
            ?? $this->whereIn('id', DB::table('product_slug_aliases')->select('product_id')->where('slug', $value))->first()
            ?? (is_numeric($value) ? $this->where('id', $value)->first() : null);
    }

    /**
     * Normalized specification rows: [['label' => '...', 'value' => '...'], ...]
     */
    public function specificationRows(): array
    {
        $rows = collect($this->specifications ?? [])
            ->map(function ($row) {
                if (is_string($row)) {
                    $row = trim($row);
                    if ($row === '') {
                        return null;
                    }
                    if (str_contains($row, ':')) {
                        [$label, $value] = array_map('trim', explode(':', $row, 2));

                        return ['label' => $label, 'value' => $value];
                    }

                    return ['label' => '', 'value' => $row];
                }

                $label = trim((string) ($row['label'] ?? ''));
                $value = trim((string) ($row['value'] ?? ''));
                if ($label === '' && $value === '') {
                    return null;
                }

                return ['label' => $label, 'value' => $value];
            })
            ->filter()
            ->values()
            ->all();

        return $rows;
    }

    /** Bullet lines for cards / PDP sidebar (e.g. "Model: Ryzen 5"). */
    public function specificationBullets(int $limit = 6): array
    {
        return collect($this->specificationRows())
            ->map(function (array $row) {
                if ($row['label'] !== '' && $row['value'] !== '') {
                    return $row['label'] . ': ' . $row['value'];
                }

                return $row['label'] !== '' ? $row['label'] : $row['value'];
            })
            ->filter()
            ->take($limit)
            ->values()
            ->all();
    }

    /** Whether this product has any variants (used in admin index / storefront card). */
    public function getHasVariantsAttribute(): bool
    {
        if ($this->relationLoaded('variants')) {
            return $this->variants->isNotEmpty();
        }
        return $this->variants()->exists();
    }

    /** Alias for storefront ProductCard (variants_exists). */
    public function getVariantsExistsAttribute(): bool
    {
        return $this->has_variants;
    }
}
