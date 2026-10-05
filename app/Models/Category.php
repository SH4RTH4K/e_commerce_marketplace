<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Category extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('menu_order')->orderBy('name');
    }

    public function selfAndDescendantIds(): array
    {
        $ids = [(int) $this->getKey()];
        $frontier = $ids;

        while ($frontier !== []) {
            $children = static::query()
                ->whereIn('parent_id', $frontier)
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
        $categories = static::query()
            ->where('is_active', true)
            ->where('show_in_menu', true)
            ->orderBy('menu_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'parent_id', 'icon', 'image']);

        return static::buildMenuBranch($categories, null);
    }

    public static function storefrontFilterTree(): array
    {
        $categories = static::query()
            ->where('is_active', true)
            ->where('show_in_menu', true)
            ->orderBy('menu_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'parent_id', 'icon', 'image']);

        return static::buildMenuBranch($categories, null, true);
    }

    private static function buildMenuBranch(Collection $categories, ?int $parentId, bool $withProductCounts = false): array
    {
        return $categories
            ->filter(fn (Category $category) => (int) ($category->parent_id ?? 0) === (int) ($parentId ?? 0))
            ->values()
            ->map(function (Category $category) use ($categories, $withProductCounts): array {
                $payload = [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'parent_id' => $category->parent_id,
                    'icon' => $category->icon,
                    'image' => $category->image,
                    'children' => static::buildMenuBranch($categories, (int) $category->id, $withProductCounts),
                ];

                if ($withProductCounts) {
                    $payload['products_count'] = $category->publishedProductsInTreeCount();
                }

                return $payload;
            })
            ->all();
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
