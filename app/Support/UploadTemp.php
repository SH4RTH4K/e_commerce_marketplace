<?php

namespace App\Support;

/**
 * Ensure a writable upload staging directory for cPanel/LiteSpeed hosts
 * where the system temp folder is missing (UPLOAD_ERR_NO_TMP_DIR).
 */
class UploadTemp
{
    public static function path(?string $basePath = null): string
    {
        $base = $basePath
            ?? (function_exists('base_path') ? base_path() : dirname(__DIR__, 2));

        return rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'tmp';
    }

    /**
     * Create storage/app/tmp and publish .user.ini for cPanel/LiteSpeed.
     * Safe to call from public/index.php before the request is handled.
     * LiteSpeed may take up to ~5 minutes to reload .user.ini.
     */
    public static function ensure(?string $basePath = null): string
    {
        $base = $basePath
            ?? (function_exists('base_path') ? base_path() : dirname(__DIR__, 2));

        $dir = self::path($base);

        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        @file_put_contents($dir . DIRECTORY_SEPARATOR . '.gitignore', "*\n!.gitignore\n");

        if (is_dir($dir) && is_writable($dir)) {
            @ini_set('upload_tmp_dir', $dir);
            @putenv('TMPDIR=' . $dir);
            @putenv('TMP=' . $dir);
            @putenv('TEMP=' . $dir);
        }

        self::writeUserIni($base, $dir);

        return $dir;
    }

    private static function writeUserIni(string $base, string $dir): void
    {
        $candidates = [];

        // Normal Laravel: docroot is /public
        $candidates[] = $base . DIRECTORY_SEPARATOR . 'public';
        // Flattened cPanel deploy: index.php lives in project root
        $candidates[] = $base;

        $normalized = str_replace('\\', '/', $dir);
        $desired = 'upload_tmp_dir = "' . $normalized . '"';

        foreach (array_unique($candidates) as $public) {
            if (! is_dir($public) || ! is_writable($public)) {
                continue;
            }
            // Only write next to an index.php (actual web root)
            if (! is_file($public . DIRECTORY_SEPARATOR . 'index.php')) {
                continue;
            }

            $userIni = $public . DIRECTORY_SEPARATOR . '.user.ini';
            $existing = is_file($userIni) ? (string) @file_get_contents($userIni) : '';
            if (str_contains($existing, 'upload_tmp_dir')) {
                continue;
            }

            $prefix = $existing !== '' && ! str_ends_with($existing, "\n") ? "\n" : '';
            @file_put_contents(
                $userIni,
                $prefix . "; DeshiMart — local upload temp (fixes missing system /tmp)\n" . $desired . "\n",
                FILE_APPEND | LOCK_EX
            );
        }
    }
}
