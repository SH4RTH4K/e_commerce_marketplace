<?php

namespace App\Models;

use App\Services\CategoryHierarchyService;
use App\Services\StorefrontPageCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class Category extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'show_in_menu' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saved(function () {
            app(StorefrontPageCache::class)->invalidate();
            Cache::forget('sitemap_urls_v2');
        });
        static::deleted(function () {
            app(StorefrontPageCache::class)->invalidate();
            Cache::forget('sitemap_urls_v2');
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('menu_order')->orderBy('name');
    }

    public function selfAndDescendantIds(bool $activeOnly = false): array
    {
        $ids = [(int) $this->getKey()];
        $frontier = $ids;

        while ($frontier !== []) {
            $children = static::query()
                ->whereIn('parent_id', $frontier)
                ->when($activeOnly, fn ($query) => $query->where('is_active', true))
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $frontier = array_values(array_diff($children, $ids));
            $ids = array_merge($ids, $frontier);
        }

        return $ids;
    }

    public function publishedProductsInTreeCount(): int
    {
        return Product::published()
            ->whereIn('category_id', $this->selfAndDescendantIds())
            ->count();
    }

    public static function storefrontMenuTree(): array
    {
        return app(CategoryHierarchyService::class)->storefrontTree();
    }

    public static function storefrontFilterTree(): array
    {
        return app(CategoryHierarchyService::class)->storefrontTree(true);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function imageUrl(): string
    {
        return image_url($this->image, $this->slug);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
