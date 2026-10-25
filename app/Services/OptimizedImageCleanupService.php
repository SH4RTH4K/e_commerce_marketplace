<?php

namespace App\Services;

use App\Models\Banner;
use App\Models\Category;
use App\Models\ProductImage;
use Illuminate\Support\Collection;
use RuntimeException;

class OptimizedImageCleanupService
{
    public function __construct(private readonly StorefrontImageService $images) {}

    /** @return array{directory:string, files:array<int,array<string,mixed>>, orphaned_count:int, orphaned_bytes:int, orphaned_formatted:string, protected_count:int} */
    public function scan(): array
    {
        $directory = public_path('uploads/optimized');
        $protected = $this->protectedVariants();
        $files = [];
        $bytes = 0;

        if (is_dir($directory)) {
            try {
                $iterator = new \FilesystemIterator($directory, \FilesystemIterator::SKIP_DOTS);
                foreach ($iterator as $file) {
                    if (! $file->isFile() || strtolower($file->getExtension()) !== 'webp') {
                        continue;
                    }
                    if (isset($protected[$file->getFilename()])) {
                        continue;
                    }

                    $size = max(0, (int) $file->getSize());
                    $modified = $file->getMTime();
                    $files[] = [
                        'filename' => $file->getFilename(),
                        'path' => 'public/uploads/optimized/'.$file->getFilename(),
                        'bytes' => $size,
                        'formatted' => $this->formatBytes($size),
                        'modified_at' => date('Y-m-d H:i:s', $modified),
                        'age_days' => max(0, (int) floor((time() - $modified) / 86400)),
                    ];
                    $bytes += $size;
                }
            } catch (\Throwable $exception) {
                throw new RuntimeException('Could not scan optimized images: '.$exception->getMessage(), 0, $exception);
            }
        }

        usort($files, fn (array $left, array $right): int => $right['bytes'] <=> $left['bytes']);

        return [
            'directory' => 'public/uploads/optimized',
            'files' => $files,
            'orphaned_count' => count($files),
            'orphaned_bytes' => $bytes,
            'orphaned_formatted' => $this->formatBytes($bytes),
            'protected_count' => count($protected),
        ];
    }

    /** @param list<string> $filenames */
    public function delete(array $filenames): array
    {
        $scan = $this->scan();
        $orphans = collect($scan['files'])->keyBy('filename');
        $deleted = [];
        $skipped = [];

        foreach (array_values(array_unique($filenames)) as $filename) {
            if (! is_string($filename) || ! preg_match('/^[a-f0-9]{64}\.webp$/i', $filename) || ! $orphans->has($filename)) {
                $skipped[] = $filename;
                continue;
            }

            $path = public_path('uploads/optimized/'.$filename);
            if (is_file($path) && @unlink($path)) {
                $deleted[] = $filename;
            } else {
                $skipped[] = $filename;
            }
        }

        return ['deleted' => $deleted, 'skipped' => $skipped];
    }

    /** @return array<string, true> */
    public function protectedVariants(): array
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
                $path = $this->images->currentOptimizedPath((string) $source, $width);
                if ($path) {
                    $protected[basename($path)] = true;
                }
            }
        }

        return $protected;
    }

    private function formatBytes(int $bytes): string
    {
        $amount = (float) $bytes;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $index = 0;
        while ($amount >= 1024 && $index < count($units) - 1) {
            $amount /= 1024;
            $index++;
        }

        return number_format($amount, $index === 0 ? 0 : 1).' '.$units[$index];
    }
}
