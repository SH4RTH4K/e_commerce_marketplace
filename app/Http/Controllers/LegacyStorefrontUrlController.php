<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Services\CategoryHierarchyService;
use Illuminate\Http\RedirectResponse;

class LegacyStorefrontUrlController extends Controller
{
    public function category(string $legacyCategory): RedirectResponse
    {
        $slug = match ($legacyCategory) {
            'customize-gift' => 'customize-and-gift',
            default => $legacyCategory,
        };

        $category = Category::query()->where('slug', $slug)->first();
        $path = $category ? app(CategoryHierarchyService::class)->path($category) : [];

        abort_if($path === [] || collect($path)->contains(fn (array $item) => ! $item['is_active']), 404);

        return redirect()->route('shop.category', $category, 301);
    }

    public function product(string $legacyProduct): RedirectResponse
    {
        $product = Product::published()->where('slug', $legacyProduct)->first();

        abort_unless($product, 404);

        return redirect()->route('product.show', $product, 301);
    }
}
