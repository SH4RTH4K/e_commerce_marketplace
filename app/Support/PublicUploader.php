<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PublicUploader
{
    /**
     * Strictly allowed upload extensions for product/media uploads.
     * SVG and ICO are intentionally excluded here — SVG files can embed
     * <script> tags and cause stored XSS when served inline. Use
     * ALLOWED_BRANDING_EXTENSIONS for logo/favicon uploads instead.
     */
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'bmp'];

    /**
     * Extended allowlist for branding assets (logo, favicon) only.
     * Admins are trusted here; SVG/ICO are needed for logos.
     */
    private const ALLOWED_BRANDING_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg', 'ico', 'bmp'];

    /**
     * Valid image MIME-type prefixes — used to verify actual file content
     * regardless of the client-provided extension.
     */
    private const ALLOWED_MIME_PREFIXES = ['image/'];

    /**
     * Store an uploaded file under public/uploads/{folder}/ (cPanel-friendly, no storage link).
     */
    public static function store(UploadedFile $file, string $folder, string $fallbackExt = 'jpg', ?string $inputKey = null): string
    {
        if (! $file->isValid()) {
            throw ValidationException::withMessages([
                $inputKey ?: 'file' => self::errorMessage($file),
            ]);
        }

        $folder = trim($folder, '/');
        $dir = public_path('uploads/' . $folder);
        self::ensureDirectory($dir);

        $ext = strtolower($file->getClientOriginalExtension() ?: $fallbackExt);
        $ext = preg_replace('/[^a-z0-9]/', '', $ext) ?: $fallbackExt;

        // Determine if this is a branding folder (logo/favicon) to use wider allowlist
        $isBranding = str_starts_with($folder, 'branding');
        $allowedExts = $isBranding ? self::ALLOWED_BRANDING_EXTENSIONS : self::ALLOWED_EXTENSIONS;

        if (! in_array($ext, $allowedExts, true)) {
            throw ValidationException::withMessages([
                $inputKey ?: 'file' => 'File type .' . $ext . ' is not allowed.',
            ]);
        }

        // SECURITY: Verify actual MIME type using finfo — rejects PHP webshells
        // disguised as images (e.g. shell.jpg with PHP content).
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $file->getRealPath());
            finfo_close($finfo);
            $mimeOk = false;
            foreach (self::ALLOWED_MIME_PREFIXES as $prefix) {
                if (str_starts_with((string) $mime, $prefix)) {
                    $mimeOk = true;
                    break;
                }
            }
            // SVG is reported as image/svg+xml or text/html — allow image/ prefix only
            if (! $mimeOk) {
                throw ValidationException::withMessages([
                    $inputKey ?: 'file' => 'The uploaded file does not appear to be a valid image (detected: ' . $mime . ').',
                ]);
            }
        }

        $filename = Str::uuid()->toString() . '.' . $ext;
        $dest = $dir . DIRECTORY_SEPARATOR . $filename;

        try {
            $file->move($dir, $filename);
        } catch (\Throwable $e) {
            $tmp = $file->getRealPath();
            if (! $tmp || (! @copy($tmp, $dest) && ! @rename($tmp, $dest))) {
                throw ValidationException::withMessages([
                    $inputKey ?: 'file' => 'Could not save the upload. On cPanel, ensure public/uploads is writable (chmod 775).',
                ]);
            }
        }

        if (! is_file($dest)) {
            throw ValidationException::withMessages([
                $inputKey ?: 'file' => 'Upload did not land on disk. Check that public/uploads/' . $folder . ' is writable.',
            ]);
        }

        return 'uploads/' . $folder . '/' . $filename;
    }

    /**
     * Store raw binary (e.g. decoded base64) — bypasses PHP $_FILES / upload_tmp_dir entirely.
     */
    public static function storeBytes(string $binary, string $folder, string $extension, ?string $inputKey = null): string
    {
        if ($binary === '') {
            throw ValidationException::withMessages([
                $inputKey ?: 'file' => 'The image data was empty.',
            ]);
        }

        $folder = trim($folder, '/');
        $dir = public_path('uploads/' . $folder);
        self::ensureDirectory($dir);

        $ext = strtolower(preg_replace('/[^a-z0-9]/', '', $extension) ?: 'jpg');

        // SECURITY: enforce strict image-only extension whitelist (branding-aware)
        $isBranding = str_starts_with($folder, 'branding');
        $allowedExts = $isBranding ? self::ALLOWED_BRANDING_EXTENSIONS : self::ALLOWED_EXTENSIONS;

        if (! in_array($ext, $allowedExts, true)) {
            throw ValidationException::withMessages([
                $inputKey ?: 'file' => 'File type .' . $ext . ' is not allowed. Allowed: jpg, jpeg, png, gif, webp, avif.',
            ]);
        }

        $filename = Str::uuid()->toString() . '.' . $ext;
        $dest = $dir . DIRECTORY_SEPARATOR . $filename;

        if (@file_put_contents($dest, $binary) === false) {
            throw ValidationException::withMessages([
                $inputKey ?: 'file' => 'Could not save the upload. On cPanel, ensure public/uploads is writable (chmod 775).',
            ]);
        }

        return 'uploads/' . $folder . '/' . $filename;
    }

    /**
     * Resolve a single file from multipart OR from base64 fields ({key}_b64 + {key}_name).
     * Base64 path avoids UPLOAD_ERR_NO_TMP_DIR on broken cPanel temp configs.
     */
    public static function fromRequest(Request $request, string $key): ?UploadedFile
    {
        $file = $request->file($key);

        if ($file instanceof UploadedFile) {
            if ($file->getError() === UPLOAD_ERR_NO_FILE) {
                // fall through to base64
            } elseif (! $file->isValid()) {
                // Prefer base64 fallback when the server temp dir is missing.
                if ($file->getError() === UPLOAD_ERR_NO_TMP_DIR && $request->filled($key . '_b64')) {
                    return null; // caller should use fromRequestBytes
                }
                throw ValidationException::withMessages([
                    $key => self::errorMessage($file),
                ]);
            } else {
                return $file;
            }
        }

        return null;
    }

    /**
     * Decode optional base64 payload posted as {key}_b64 (+ optional {key}_name).
     *
     * @return array{0: string, 1: string}|null [binary, extension]
     */
    public static function decodeBase64Payload(string $b64, string $name = 'upload.jpg', ?string $inputKey = null): ?array
    {
        $b64 = trim($b64);
        if ($b64 === '') {
            return null;
        }

        if (preg_match('/^data:([^;]+);base64,(.+)$/s', $b64, $m)) {
            $b64 = $m[2];
        }

        $binary = base64_decode($b64, true);
        if ($binary === false || $binary === '') {
            throw ValidationException::withMessages([
                $inputKey ?: 'file' => 'Could not decode the uploaded image. Please try another file.',
            ]);
        }

        // 12MB decoded soft limit
        if (strlen($binary) > 12 * 1024 * 1024) {
            throw ValidationException::withMessages([
                $inputKey ?: 'file' => 'The image is too large. Please use a file under 10MB.',
            ]);
        }

        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION) ?: 'jpg');
        $ext = preg_replace('/[^a-z0-9]/', '', $ext) ?: 'jpg';

        // SECURITY: enforce strict image-only extension whitelist on base64 path.
        // If ext is not in the general allowed list, safely coerce to jpg.
        // (Branding uploads via base64 are not common so we use the conservative list.)
        if (! in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            $ext = 'jpg'; // safely coerce to jpg rather than reject
        }

        return [$binary, $ext];
    }

    /**
     * Decode optional base64 payload from request.
     *
     * @return array{0: string, 1: string}|null [binary, extension]
     */
    public static function bytesFromRequest(Request $request, string $key): ?array
    {
        $b64 = $request->input($key . '_b64');
        if (! is_string($b64) || trim($b64) === '') {
            return null;
        }

        $name = (string) $request->input($key . '_name', 'upload.jpg');

        return self::decodeBase64Payload($b64, $name, $key);
    }

    /**
     * Multipart file or base64 → stored public relative path, or null if nothing sent.
     */
    public static function storeFromRequest(Request $request, string $key, string $folder, string $fallbackExt = 'jpg'): ?string
    {
        // 1. Prefer Base64 payload if provided (bypasses server temp dir entirely)
        $bytes = self::bytesFromRequest($request, $key);
        if ($bytes) {
            return self::storeBytes($bytes[0], $folder, $bytes[1] ?: $fallbackExt, $key);
        }

        // 2. Otherwise process multipart file
        $file = self::fromRequest($request, $key);
        if ($file) {
            return self::store($file, $folder, $fallbackExt, $key);
        }

        // Surface multipart temp-dir failures when no base64 fallback was provided.
        $raw = $request->file($key);
        if ($raw instanceof UploadedFile && $raw->getError() !== UPLOAD_ERR_NO_FILE && ! $raw->isValid()) {
            throw ValidationException::withMessages([
                $key => self::errorMessage($raw),
            ]);
        }

        return null;
    }

    /**
     * @return list<UploadedFile>
     */
    public static function manyFromRequest(Request $request, string $key): array
    {
        $files = $request->file($key);

        if ($files === null) {
            return [];
        }

        if (! is_array($files)) {
            $files = [$files];
        }

        $valid = [];
        foreach ($files as $index => $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }
            if ($file->getError() === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if (! $file->isValid()) {
                throw ValidationException::withMessages([
                    "{$key}.{$index}" => self::errorMessage($file),
                ]);
            }
            $valid[] = $file;
        }

        return $valid;
    }

    /**
     * Product multi-image: multipart and/or images_b64[] + images_name[].
     *
     * @return list<string> stored relative paths
     */
    public static function storeManyFromRequest(Request $request, string $key, string $folder): array
    {
        $paths = [];

        // 1. Process Base64 image array if present
        $b64List = $request->input($key . '_b64', []);
        $nameList = $request->input($key . '_name', []);
        if (! is_array($b64List)) {
            $b64List = $b64List ? [$b64List] : [];
        }
        if (! is_array($nameList)) {
            $nameList = $nameList ? [$nameList] : [];
        }

        if ($b64List !== []) {
            foreach ($b64List as $i => $b64) {
                if (! is_string($b64) || trim($b64) === '') {
                    continue;
                }
                $name = (string) ($nameList[$i] ?? ('image_' . $i . '.jpg'));
                $decoded = self::decodeBase64Payload($b64, $name, "{$key}.{$i}");
                if ($decoded) {
                    $paths[] = self::storeBytes($decoded[0], $folder, $decoded[1], "{$key}.{$i}");
                }
            }

            if ($paths !== []) {
                return $paths;
            }
        }

        // 2. Process standard multipart files
        try {
            foreach (self::manyFromRequest($request, $key) as $index => $file) {
                $paths[] = self::store($file, $folder, 'jpg', "{$key}.{$index}");
            }
        } catch (ValidationException $e) {
            // If temp dir is broken, fall through if any paths were saved
            if ($paths === []) {
                throw $e;
            }
        }

        return $paths;
    }

    public static function delete(?string $path): void
    {
        if (! $path || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return;
        }

        $relative = ltrim($path, '/');
        if (! str_starts_with($relative, 'uploads/')) {
            return;
        }

        $full = public_path($relative);
        if (is_file($full)) {
            @unlink($full);
        }
    }

    private static function ensureDirectory(string $dir): void
    {
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw ValidationException::withMessages([
                'file' => 'Could not create upload folder. On cPanel, create public/uploads and set permissions to 775.',
            ]);
        }

        if (! is_writable($dir)) {
            throw ValidationException::withMessages([
                'file' => 'Upload folder is not writable. On cPanel, set public/uploads to chmod 775 (or 755 if owned by the PHP user).',
            ]);
        }
    }

    private static function errorMessage(UploadedFile $file): string
    {
        $tmp = str_replace('\\', '/', UploadTemp::path());

        return match ($file->getError()) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The image is larger than the server allows. Raise upload_max_filesize / post_max_size in cPanel (try 16M), or use a smaller file.',
            UPLOAD_ERR_PARTIAL => 'The image upload was interrupted. Please try again.',
            UPLOAD_ERR_NO_TMP_DIR => 'PHP temporary folder is missing on this server. In cPanel → MultiPHP INI Editor set upload_tmp_dir to ' . $tmp . ' (and chmod 775 that folder), then retry. DeshiMart also sends images as base64 to work around this.',
            UPLOAD_ERR_CANT_WRITE => 'Server could not write the uploaded file. Check disk space and permissions.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension blocked the upload.',
            default => 'The image failed to upload (error ' . $file->getError() . ').',
        };
    }
}
