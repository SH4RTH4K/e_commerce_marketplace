<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Models\Category;
use App\Models\ProductImage;
use App\Services\CategoryHierarchyService;
use App\Services\StorefrontImageService;

class StorefrontImageController extends Controller
{
    public function __invoke(string $kind, int $id, int $width, StorefrontImageService $images)
    {
        abort_unless(in_array($width, [160, 320, 640, 960, 1280], true), 404);
        $source = match ($kind) {
            'product' => ProductImage::whereHas('product', fn ($query) => $query->published())->findOrFail($id)->path,
            'banner' => Banner::active()->findOrFail($id)->image,
            'category' => $this->categoryImage($id),
            'logo' => $id === 0 ? setting('logo', '') : null,
            default => null,
        };
        abort_unless(is_string($source) && $source !== '', 404);
        try {
            $url = $images->optimized($source, $width);
            // Optimized paths include a content/version hash, so they can be
            // cached for a year without serving stale images after updates.
            return redirect($url)->header('Cache-Control', 'public, max-age=31536000, immutable');
        } catch (\Throwable $exception) {
            // A supplier failure or unsupported format must not remove imagery.
            return redirect(image_url($source))->header('Cache-Control', 'public, max-age=60');
        }
    }

    private function categoryImage(int $id): ?string
    {
        $category = Category::findOrFail($id);
        $path = app(CategoryHierarchyService::class)->path($category);
        abort_unless($path !== [] && collect($path)->every(fn ($item) => $item['is_active']), 404);
        return $category->image;
    }
}
