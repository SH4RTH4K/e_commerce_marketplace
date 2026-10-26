<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\CategoryHierarchyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LegacyStorefrontUrlController extends Controller
{
    public function category(Request $request, string $legacyCategory): RedirectResponse
    {
        $slug = match ($legacyCategory) {
            'customize-gift' => 'customize-and-gift',
            default => $legacyCategory,
        };

        $category = Category::query()->where('slug', $slug)->first();
        $path = $category ? app(CategoryHierarchyService::class)->path($category) : [];

        abort_if($path === [] || collect($path)->contains(fn (array $item) => ! $item['is_active']), 404);

        $destination = route('shop.category', $category, false);

        return redirect()->away($request->getSchemeAndHttpHost().$destination, 301);
    }
}
