<?php

namespace App\Services;

use App\Models\Banner;
use App\Models\Category;
use App\Models\ProductImage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class OptimizedImageCleanupService
{
    private const TRASH_DIRECTORY = 'optimized-image-trash';

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
                        'status' => 'orphaned',
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
    public function quarantine(array $filenames): array
    {
        $scan = $this->scan();
        $orphans = collect($scan['files'])->keyBy('filename');
        $quarantined = [];
        $skipped = [];
        $batchDirectory = storage_path('app/private/'.self::TRASH_DIRECTORY.'/'.now()->format('Ymd_His').'-'.Str::lower(Str::random(8)));

        foreach (array_values(array_unique($filenames)) as $filename) {
            if (! is_string($filename) || ! preg_match('/^[a-f0-9]{64}\.webp$/i', $filename) || ! $orphans->has($filename)) {
                $skipped[] = $filename;
                continue;
            }

            $path = public_path('uploads/optimized/'.$filename);
            $destination = $batchDirectory.'/'.$filename;
            if (is_file($path)) {
                File::ensureDirectoryExists($batchDirectory, 0700);
                if (@rename($path, $destination)) {
                    $quarantined[] = $filename;
                } else {
                    $skipped[] = $filename;
                }
            } else {
                $skipped[] = $filename;
            }
        }

        if ($quarantined === [] && is_dir($batchDirectory)) {
            File::deleteDirectory($batchDirectory);
        }

        return ['quarantined' => $quarantined, 'skipped' => $skipped];
    }

    /** @param list<string> $filenames */
    public function delete(array $filenames): array
    {
        return $this->quarantine($filenames);
    }

    /** @return array{purged:int, bytes:int, formatted:string} */
    public function purgeTrash(?int $retentionDays = null): array
    {
        $retentionDays = max(1, $retentionDays ?? (int) config('optimized-images.trash_retention_days', 30));
        $root = storage_path('app/private/'.self::TRASH_DIRECTORY);
        $cutoff = now()->subDays($retentionDays)->timestamp;
        $purged = 0;
        $bytes = 0;

        if (! is_dir($root)) {
            return ['purged' => 0, 'bytes' => 0, 'formatted' => $this->formatBytes(0)];
        }

        foreach (File::directories($root) as $directory) {
            $modified = @filemtime($directory);
            if ($modified === false || $modified > $cutoff) {
                continue;
            }

            $bytes += $this->directoryBytes($directory);
            File::deleteDirectory($directory);
            $purged++;
        }

        return ['purged' => $purged, 'bytes' => $bytes, 'formatted' => $this->formatBytes($bytes)];
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

    private function directoryBytes(string $directory): int
    {
        $bytes = 0;
        foreach (File::allFiles($directory) as $file) {
            $bytes += max(0, (int) $file->getSize());
        }

        return $bytes;
    }
}
