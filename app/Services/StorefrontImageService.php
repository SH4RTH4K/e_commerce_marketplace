<?php

namespace App\Services;

use App\Services\Dropshipping\Security\SupplierImageUrlValidator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class StorefrontImageService
{
    public const WIDTHS = [160, 320, 640, 960, 1280];

    public function optimized(string $source, int $width): string
    {
        if (! function_exists('imagewebp')) throw new RuntimeException('WebP is unavailable.');
        if (! in_array($width, self::WIDTHS, true)) throw new RuntimeException('Unsupported image width.');
        $local = $this->localPath($source);
        $version = $local ? filemtime($local) : intdiv(time(), 604800);
        $relative = $this->optimizedRelativePath($source, $width, (int) $version);
        $key = pathinfo($relative, PATHINFO_FILENAME);
        $target = public_path($relative);
        if (is_file($target)) return '/'.$relative;

        Cache::lock('storefront-image-'.$key, 30)->block(20, function () use ($source, $local, $width, $target) {
            if (is_file($target)) return;
            $bytes = $local ? file_get_contents($local) : $this->download($source);
            if (! is_string($bytes) || strlen($bytes) > 12 * 1024 * 1024) throw new RuntimeException('Image exceeds size limit.');
            $info = @getimagesizefromstring($bytes);
            if (! $info || $info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > 12_000_000) throw new RuntimeException('Image dimensions are unsupported.');
            // Preserve animated GIFs rather than silently discard their frames.
            if ($info[2] === IMAGETYPE_GIF) throw new RuntimeException('GIF remains in its original format.');
            $original = @imagecreatefromstring($bytes);
            if (! $original) throw new RuntimeException('Cannot decode image.');
            $scale = min(1, $width / max($info[0], $info[1]));
            $resized = imagecreatetruecolor(max(1, (int) round($info[0] * $scale)), max(1, (int) round($info[1] * $scale)));
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            imagecopyresampled($resized, $original, 0, 0, 0, 0, imagesx($resized), imagesy($resized), $info[0], $info[1]);
            if (! is_dir(dirname($target))) mkdir(dirname($target), 0775, true);
            $temporary = $target.'.'.bin2hex(random_bytes(5)).'.tmp';
            try {
                if (! imagewebp($resized, $temporary, 82) || ! rename($temporary, $target)) throw new RuntimeException('Cannot save optimized image.');
            } finally {
                if (is_file($temporary)) unlink($temporary);
                imagedestroy($original); imagedestroy($resized);
            }
        });

        return '/'.$relative;
    }

    /** Return the current generated path without downloading or creating an image. */
    public function currentOptimizedPath(string $source, int $width): ?string
    {
        if (! in_array($width, self::WIDTHS, true)) {
            return null;
        }

        try {
            $local = $this->localPath($source);
            $version = $local ? filemtime($local) : intdiv(time(), 604800);

            return $this->optimizedRelativePath($source, $width, (int) $version);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Remove generated variants for a source before its original is replaced or deleted. */
    public function forgetOptimizedVariants(string $source): int
    {
        $versions = [];
        try {
            $local = $this->localPath($source);
            if ($local) {
                $versions[] = (int) filemtime($local);
            }
        } catch (\Throwable) {
            // The nightly orphan sweep will handle invalid or already-missing sources.
        }

        if ($versions === []) {
            $current = intdiv(time(), 604800);
            $versions = [$current, $current - 1, $current - 2];
        }

        $deleted = 0;
        foreach (array_unique($versions) as $version) {
            foreach (self::WIDTHS as $width) {
                $target = public_path($this->optimizedRelativePath($source, $width, (int) $version));
                if (is_file($target) && @unlink($target)) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    private function optimizedRelativePath(string $source, int $width, int $version): string
    {
        $key = hash('sha256', $source.'|'.$width.'|'.$version);

        return 'uploads/optimized/'.$key.'.webp';
    }

    private function localPath(string $source): ?string
    {
        $url = parse_url(image_url($source));
        $ownHost = parse_url(config('app.url'), PHP_URL_HOST);
        if (isset($url['host']) && strcasecmp($url['host'], (string) $ownHost) !== 0) return null;
        $path = rawurldecode($url['path'] ?? '');
        if (! preg_match('~^/(uploads|storage)/~', $path)) throw new RuntimeException('Only public images can be optimized.');
        $absolute = realpath(public_path(ltrim($path, '/')));
        $root = realpath(public_path(explode('/', trim($path, '/'))[0]));
        if (! $absolute || ! $root || ! str_starts_with($absolute, $root.DIRECTORY_SEPARATOR) || ! is_file($absolute)) throw new RuntimeException('Image is missing.');
        if (filesize($absolute) > 12 * 1024 * 1024) throw new RuntimeException('Image exceeds size limit.');
        return $absolute;
    }

    private function download(string $source): string
    {
        $source = image_url($source);
        $host = parse_url($source, PHP_URL_HOST);
        $validated = app(SupplierImageUrlValidator::class)->validate($source, [(string) $host]);
        $ips = array_map(fn ($ip) => str_contains($ip, ':') ? '['.$ip.']' : $ip, $validated->resolvedIps);
        $response = Http::withOptions([
            'allow_redirects' => false, 'stream' => true,
            // Guzzle otherwise switches streaming requests to StreamHandler,
            // which ignores CURLOPT_RESOLVE and would undo DNS pinning.
            'handler' => new \GuzzleHttp\Handler\CurlHandler,
            'progress' => static function ($total, $received) {
                if ($total > 12 * 1024 * 1024 || $received > 12 * 1024 * 1024) throw new RuntimeException('Image exceeds size limit.');
            },
            'verify' => \Composer\CaBundle\CaBundle::getBundledCaBundlePath(),
            'curl' => [CURLOPT_RESOLVE => [$validated->host.':443:'.implode(',', $ips)]],
        ])->connectTimeout(5)->timeout(20)->get($source);
        if (! $response->successful() || (int) $response->header('Content-Length') > 12 * 1024 * 1024) throw new RuntimeException('Image fetch failed.');
        $stream = $response->toPsrResponse()->getBody();
        $bytes = '';
        try {
            while (! $stream->eof()) {
                $bytes .= $stream->read(65536);
                if (strlen($bytes) > 12 * 1024 * 1024) throw new RuntimeException('Image exceeds size limit.');
            }
        } finally { $stream->close(); }
        return $bytes;
    }
}
