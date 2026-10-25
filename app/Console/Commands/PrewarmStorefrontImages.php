<?php

namespace App\Console\Commands;

use App\Models\ProductImage;
use App\Services\StorefrontImageService;
use Illuminate\Console\Command;

class PrewarmStorefrontImages extends Command
{
    protected $signature = 'storefront:prewarm-images
        {--product= : Only warm images belonging to this product ID}
        {--limit= : Maximum number of product images to warm}
        {--width= : Warm one width instead of the default 320 and 960 variants}';

    protected $description = 'Generate cached storefront image variants before customers request them';

    public function handle(StorefrontImageService $images): int
    {
        $widths = $this->option('width') === null
            ? [320, 960]
            : [(int) $this->option('width')];
        $widths = array_values(array_filter($widths, fn (int $width): bool => in_array($width, [160, 320, 640, 960, 1280], true)));

        if ($widths === []) {
            $this->error('Width must be one of: 160, 320, 640, 960, 1280.');

            return self::FAILURE;
        }

        $query = ProductImage::query()
            ->whereHas('product', fn ($products) => $products->published())
            ->when($this->option('product'), fn ($imagesQuery, $productId) => $imagesQuery->where('product_id', (int) $productId))
            ->orderBy('id');
        $limit = $this->option('limit') !== null ? max(1, (int) $this->option('limit')) : null;
        $warmed = 0;
        $failed = 0;

        $query->when($limit, fn ($imagesQuery) => $imagesQuery->limit($limit))
            ->get()
            ->each(function (ProductImage $image) use ($images, $widths, &$warmed, &$failed): void {
                foreach ($widths as $width) {
                    try {
                        $images->optimized((string) $image->path, $width);
                        $warmed++;
                    } catch (\Throwable $exception) {
                        $failed++;
                        $this->warn("Image {$image->getKey()} ({$width}px): {$exception->getMessage()}");
                    }
                }
            });

        $this->info("Warmed {$warmed} image variants; {$failed} failed.");

        return self::SUCCESS;
    }
}
