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
        $productImage = null;
        if ($kind === 'product') {
            $productImage = ProductImage::whereHas('product', fn ($query) => $query->published())->findOrFail($id);
            $source = $productImage->path;
        } else {
            $source = match ($kind) {
                'banner' => Banner::active()->findOrFail($id)->image,
                'category' => $this->categoryImage($id),
                'logo' => $id === 0 ? setting('logo', '') : null,
                default => null,
            };
        }
        abort_unless(is_string($source) && $source !== '', 404);
        try {
            $url = $images->optimized($source, $width);
            // Optimized paths include a content/version hash, so they can be
            // cached for a year without serving stale images after updates.
            return redirect($url)->header('Cache-Control', 'public, max-age=31536000, immutable');
        } catch (\Throwable $exception) {
            // If a supplier image is invalid, serve another image from the same
            // product automatically. This keeps cards and product pages usable
            // even when a supplier URL returns HTML, times out, or changes.
            if ($productImage) {
                $fallbackImages = ProductImage::query()
                    ->where('product_id', $productImage->product_id)
                    ->where('id', '!=', $productImage->getKey())
                    ->orderByDesc('is_primary')
                    ->orderBy('position')
                    ->get();

                foreach ($fallbackImages as $fallbackImage) {
                    try {
                        $fallbackUrl = $images->optimized((string) $fallbackImage->path, $width);
                        return redirect($fallbackUrl)->header('Cache-Control', 'public, max-age=31536000, immutable');
                    } catch (\Throwable $fallbackException) {
                        continue;
                    }
                }
            }

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
