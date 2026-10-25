<?php

namespace App\Jobs;

use App\Models\ProductImage;
use App\Services\StorefrontImageService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class WarmProductImageVariants implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public readonly int $productImageId) {}

    public function handle(StorefrontImageService $images): void
    {
        $image = ProductImage::find($this->productImageId);
        if ($image === null || trim((string) $image->path) === '') {
            return;
        }

        foreach ([320, 960] as $width) {
            try {
                $images->optimized((string) $image->path, $width);
            } catch (\Throwable) {
                // Supplier URLs can be temporarily unavailable. The storefront
                // endpoint will retain its existing fallback behavior.
            }
        }
    }
}
