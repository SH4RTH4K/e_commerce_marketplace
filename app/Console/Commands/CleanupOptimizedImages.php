<?php

namespace App\Console\Commands;

use App\Models\Banner;
use App\Models\Category;
use App\Models\ProductImage;
use App\Services\StorefrontImageService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class CleanupOptimizedImages extends Command
{
    protected $signature = 'storefront:cleanup-optimized-images
        {--grace-days=7 : Keep unreferenced variants newer than this many days}
        {--dry-run : Report orphaned files without deleting them}';

    protected $description = 'Remove orphaned generated storefront image variants';

    public function handle(StorefrontImageService $images): int
    {
        $graceDays = max(0, (int) $this->option('grace-days'));
        $cutoff = now()->subDays($graceDays)->getTimestamp();
        $protected = $this->protectedVariants($images);
        $directory = public_path('uploads/optimized');
        $orphaned = 0;
        $deleted = 0;

        if (! is_dir($directory)) {
            $this->info('No optimized image directory exists.');

            return self::SUCCESS;
        }

        try {
            $iterator = new \FilesystemIterator($directory, \FilesystemIterator::SKIP_DOTS);
            foreach ($iterator as $file) {
                if (! $file->isFile() || strtolower($file->getExtension()) !== 'webp' || $file->getMTime() > $cutoff) {
                    continue;
                }
                if (isset($protected[$file->getFilename()])) {
                    continue;
                }

                $orphaned++;
                if (! $this->option('dry-run') && @unlink($file->getPathname())) {
                    $deleted++;
                }
            }
        } catch (\Throwable $exception) {
            $this->error('Could not scan optimized images: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info(($this->option('dry-run') ? 'Found ' : 'Removed ').$orphaned.' orphaned optimized image(s).');
        if (! $this->option('dry-run')) {
            $this->line('Deleted: '.$deleted.'; protected current variants: '.count($protected).'.');
        }

        return self::SUCCESS;
    }

    /** @return array<string, true> */
    private function protectedVariants(StorefrontImageService $images): array
    {
        $sources = new Collection();
        $sources = $sources
            ->merge(ProductImage::query()->pluck('path'))
            ->merge(Category::query()->pluck('image'))
            ->merge(Banner::query()->pluck('image'))
            ->push(setting('logo', ''))
            ->filter(fn ($source) => is_string($source) && trim($source) !== '')
            ->unique();

        $protected = [];
        foreach ($sources as $source) {
            foreach (StorefrontImageService::WIDTHS as $width) {
                $path = $images->currentOptimizedPath((string) $source, $width);
                if ($path) {
                    $protected[basename($path)] = true;
                }
            }
        }

        return $protected;
    }
}
