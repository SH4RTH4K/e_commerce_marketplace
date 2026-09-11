<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PharData;
use RuntimeException;

/**
 * Creates and restores private, administrator-triggered store backups.
 *
 * Backup archives never live below public/ and media restores are limited to
 * the application's known public media roots.
 */
class DatabaseBackupService
{
    private const MEDIA_DIRECTORIES = [
        'uploads',
        'asset/front-end/img',
    ];

    public function create(?string $createdBy = 'scheduler'): int
    {
        $filename = 'ecommerce-'.now()->format('Y-m-d-His').'.sql.gz';
        $id = $this->startRecord($filename, $createdBy);
        $absolute = $this->backupPath($filename);
        $handle = null;

        try {
            $this->ensureBackupDirectory();
            $handle = gzopen($absolute, 'wb9');
            if ($handle === false) {
                throw new RuntimeException('Unable to create the compressed backup file.');
            }

            $this->write($handle, "-- Ecommerce database backup\n-- Created: ".now()->toIso8601String()."\n");
            $this->write($handle, $this->foreignKeyChecks(false));

            foreach ($this->tables() as $table) {
                $definition = $this->tableDefinition($table);
                if ($definition !== null) {
                    $this->write($handle, 'DROP TABLE IF EXISTS '.$this->quoteIdentifier($table).";\n");
                    $this->write($handle, rtrim($definition, ';').";\n");
                }

                DB::table($table)->orderByRaw('1')->chunk(250, function ($rows) use ($handle, $table): void {
                    foreach ($rows as $row) {
                        $values = array_map(
                            fn ($value) => $this->quoteValue($value),
                            array_values((array) $row),
                        );

                        $columns = array_map(
                            fn ($column) => $this->quoteIdentifier((string) $column),
                            array_keys((array) $row),
                        );

                        $this->write(
                            $handle,
                            'INSERT INTO '.$this->quoteIdentifier($table).' ('.implode(',', $columns).') VALUES ('.implode(',', $values).');'."\n",
                        );
                    }
                });

                $this->write($handle, "\n");
            }

            $this->write($handle, $this->foreignKeyChecks(true));
            gzclose($handle);
            $handle = null;

            $size = filesize($absolute);
            if ($size === false) {
                throw new RuntimeException('The backup file was created but its size could not be read.');
            }

            $this->completeRecord($id, (int) $size);
            $this->prune();

            return $id;
        } catch (\Throwable $exception) {
            if (is_resource($handle)) {
                gzclose($handle);
            }
            @unlink($absolute);
            $this->failRecord($id, $exception->getMessage());
            throw $exception;
        }
    }

    public function createMedia(?string $createdBy = 'scheduler'): int
    {
        $filename = 'ecommerce-'.now()->format('Y-m-d-His').'.media.tar.gz';
        $id = $this->startRecord($filename, $createdBy);
        $tarPath = $this->backupPath(substr($filename, 0, -3));
        $archivePath = $tarPath.'.gz';

        try {
            $this->ensureBackupDirectory();
            $archive = new PharData($tarPath);
            $this->addMediaFiles($archive);
            $archive->compress(\Phar::GZ);
            unset($archive);
            @unlink($tarPath);

            $size = filesize($archivePath);
            if ($size === false) {
                throw new RuntimeException('The media backup was created but its size could not be read.');
            }

            $this->completeRecord($id, (int) $size);
            $this->prune();

            return $id;
        } catch (\Throwable $exception) {
            @unlink($tarPath);
            @unlink($archivePath);
            $this->failRecord($id, $exception->getMessage());
            throw $exception;
        }
    }

    public function createFull(?string $createdBy = 'scheduler'): int
    {
        $databaseId = $this->create($createdBy);
        $database = DB::table('system_backups')->find($databaseId);

        if (! $database) {
            throw new RuntimeException('The database backup record could not be found.');
        }

        $filename = preg_replace('/\.sql\.gz$/', '.full.tar.gz', $database->filename);
        $filename = is_string($filename) ? $filename : $database->filename.'.full.tar.gz';
        $tarPath = $this->backupPath(substr($filename, 0, -3));
        $archivePath = $tarPath.'.gz';
        $databasePath = $this->backupPath($database->filename);

        try {
            $archive = new PharData($tarPath);
            $archive->addFile($databasePath, 'database.sql.gz');
            $this->addMediaFiles($archive);
            $archive->compress(\Phar::GZ);
            unset($archive);

            @unlink($tarPath);
            @unlink($databasePath);

            $size = filesize($archivePath);
            if ($size === false) {
                throw new RuntimeException('The full backup was created but its size could not be read.');
            }

            DB::table('system_backups')->where('id', $databaseId)->update([
                'filename' => $filename,
                'size_bytes' => $size,
                'updated_at' => now(),
            ]);

            return $databaseId;
        } catch (\Throwable $exception) {
            @unlink($tarPath);
            @unlink($archivePath);
            $this->failRecord($databaseId, $exception->getMessage());
            throw $exception;
        }
    }

    public function restore(string $path): void
    {
        $handle = @gzopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Unable to read the compressed database backup.');
        }

        $statement = '';
        $single = false;
        $double = false;
        $backtick = false;
        $escaped = false;

        $execute = function (string $sql): void {
            $sql = trim($sql);
            if ($sql === '' || preg_match('/^(--|#)/', $sql)) {
                return;
            }

            if (preg_match('/^(SET\s+FOREIGN_KEY_CHECKS|PRAGMA\s+foreign_keys)/i', $sql)) {
                return;
            }

            DB::unprepared(rtrim($sql, ';').';');
        };

        try {
            DB::unprepared($this->foreignKeyChecks(false));

            while (! gzeof($handle)) {
                $chunk = gzread($handle, 8192);
                if ($chunk === false) {
                    throw new RuntimeException('Unable to read the database backup stream.');
                }

                for ($index = 0, $length = strlen($chunk); $index < $length; $index++) {
                    $char = $chunk[$index];
                    $statement .= $char;

                    if ($escaped) {
                        $escaped = false;

                        continue;
                    }
                    if (($single || $double) && $char === '\\') {
                        $escaped = true;

                        continue;
                    }
                    if (! $double && ! $backtick && $char === "'") {
                        $single = ! $single;

                        continue;
                    }
                    if (! $single && ! $backtick && $char === '"') {
                        $double = ! $double;

                        continue;
                    }
                    if (! $single && ! $double && $char === '`') {
                        $backtick = ! $backtick;

                        continue;
                    }
                    if ($char === ';' && ! $single && ! $double && ! $backtick) {
                        $execute($statement);
                        $statement = '';
                    }
                }
            }

            $execute($statement);
            DB::unprepared($this->foreignKeyChecks(true));
        } catch (\Throwable $exception) {
            try {
                DB::unprepared($this->foreignKeyChecks(true));
            } catch (\Throwable) {
                // Preserve the original restore failure.
            }
            throw $exception;
        } finally {
            gzclose($handle);
        }
    }

    public function restoreMedia(string $path): void
    {
        $this->restoreArchive($path, false);
    }

    public function restoreFull(string $path): void
    {
        $this->restoreArchive($path, true);
    }

    /** @param PharData $archive */
    private function addMediaFiles($archive): void
    {
        foreach (self::MEDIA_DIRECTORIES as $directory) {
            $root = public_path($directory);
            if (! is_dir($root)) {
                continue;
            }

            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            );

            foreach ($files as $file) {
                if (! $file->isFile() || $file->isLink()) {
                    continue;
                }

                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen(public_path()) + 1));
                $archive->addFile($file->getPathname(), 'media/'.$relative);
            }
        }
    }

    private function restoreArchive(string $path, bool $full): void
    {
        $temporaryRoot = storage_path('app/private/restore-'.Str::random(24));
        $tarPath = preg_replace('/\.gz$/i', '', $path);
        if (! is_string($tarPath)) {
            throw new RuntimeException('Invalid archive path.');
        }

        try {
            $archive = new PharData($path);
            if (! is_file($tarPath)) {
                $archive->decompress();
            }
            $archive = new PharData($tarPath);
            $this->validateArchiveEntries($archive, $full);
            $archive->extractTo($temporaryRoot, null, true);

            if ($full) {
                $databasePath = $temporaryRoot.'/database.sql.gz';
                if (! is_file($databasePath)) {
                    throw new RuntimeException('The full backup does not contain a database backup.');
                }
                $this->restore($databasePath);
            }

            $this->restoreMediaDirectory($temporaryRoot.'/media');
        } finally {
            @unlink($tarPath);
            if (is_dir($temporaryRoot)) {
                File::deleteDirectory($temporaryRoot);
            }
        }
    }

    private function restoreMediaDirectory(string $mediaRoot): void
    {
        if (! is_dir($mediaRoot)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($mediaRoot, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($mediaRoot) + 1));
            $destination = $this->safeMediaDestination($relative);
            if (! is_dir(dirname($destination)) && ! mkdir(dirname($destination), 0755, true) && ! is_dir(dirname($destination))) {
                throw new RuntimeException('Unable to create the media restore directory.');
            }
            if (! copy($file->getPathname(), $destination)) {
                throw new RuntimeException('Unable to restore media file: '.$relative);
            }
        }
    }

    private function validateArchiveEntries(PharData $archive, bool $full): void
    {
        $iterator = new \RecursiveIteratorIterator($archive, \RecursiveIteratorIterator::SELF_FIRST);

        foreach ($iterator as $entry => $file) {
            $entry = $this->archiveRelativePath((string) $entry);
            if ($entry === '' || $entry === '.' || $entry === 'media') {
                continue;
            }

            if (str_contains($entry, '..') || str_starts_with($entry, '/') || preg_match('#^[A-Za-z]:/#', $entry)) {
                throw new RuntimeException('The backup contains an unsafe archive path.');
            }

            if ($full && $entry !== 'database.sql.gz' && ! str_starts_with($entry, 'media/')) {
                throw new RuntimeException('The full backup contains an unsupported file.');
            }
            if (! $full && ! str_starts_with($entry, 'media/')) {
                throw new RuntimeException('The media backup contains an unsupported file.');
            }
        }
    }

    private function safeMediaDestination(string $relative): string
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        foreach (self::MEDIA_DIRECTORIES as $directory) {
            if ($relative === $directory || str_starts_with($relative, $directory.'/')) {
                $destination = public_path($relative);
                $publicRoot = realpath(public_path()) ?: public_path();
                $parent = dirname($destination);
                $parentRoot = realpath($parent);
                if ($parentRoot !== false && ! str_starts_with($parentRoot, rtrim($publicRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR) && $parentRoot !== $publicRoot) {
                    throw new RuntimeException('The backup contains an unsafe media path.');
                }

                return $destination;
            }
        }

        throw new RuntimeException('The backup contains media outside the allowed upload directories.');
    }

    private function archiveRelativePath(string $entry): string
    {
        $entry = str_replace('\\', '/', trim($entry));
        if (str_starts_with($entry, 'phar://')) {
            $mediaPosition = strpos($entry, '/media');
            $databasePosition = strpos($entry, '/database.sql.gz');
            if ($mediaPosition !== false && ($databasePosition === false || $mediaPosition < $databasePosition)) {
                $entry = substr($entry, $mediaPosition + 1);
            } elseif ($databasePosition !== false) {
                $entry = substr($entry, $databasePosition + 1);
            }
        }

        return ltrim($entry, '/');
    }

    private function tables(): array
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return collect(DB::select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' AND name != 'system_backups' ORDER BY name"))
                ->map(fn ($row) => (string) $row->name)
                ->all();
        }

        return collect(DB::select('SHOW TABLES'))
            ->map(fn ($row) => (string) array_values((array) $row)[0])
            ->reject(fn (string $table) => $table === 'system_backups')
            ->all();
    }

    private function tableDefinition(string $table): ?string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            $definition = DB::selectOne("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ?", [$table]);

            return $definition?->sql;
        }

        $definition = DB::selectOne('SHOW CREATE TABLE '.$this->quoteIdentifier($table));

        return $definition ? (string) array_values((array) $definition)[1] : null;
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`'.str_replace('`', '``', $identifier).'`';
    }

    private function quoteValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        return DB::connection()->getPdo()->quote((string) $value);
    }

    private function foreignKeyChecks(bool $enable): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? 'PRAGMA foreign_keys = '.($enable ? 'ON' : 'OFF').';'
            : 'SET FOREIGN_KEY_CHECKS = '.($enable ? '1' : '0').';';
    }

    private function write($handle, string $contents): void
    {
        if (gzwrite($handle, $contents) === false) {
            throw new RuntimeException('Unable to write the database backup.');
        }
    }

    private function startRecord(string $filename, ?string $createdBy): int
    {
        return (int) DB::table('system_backups')->insertGetId([
            'filename' => $filename,
            'disk' => config('system-health.backup_disk', 'local'),
            'status' => 'creating',
            'created_by' => $createdBy,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function completeRecord(int $id, int $size): void
    {
        DB::table('system_backups')->where('id', $id)->update([
            'status' => 'completed',
            'size_bytes' => $size,
            'updated_at' => now(),
        ]);
    }

    private function failRecord(int $id, string $message): void
    {
        DB::table('system_backups')->where('id', $id)->update([
            'status' => 'failed',
            'error' => mb_substr($message, 0, 2000),
            'updated_at' => now(),
        ]);
    }

    private function ensureBackupDirectory(): void
    {
        $directory = dirname($this->backupPath('placeholder'));
        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create the private backup directory.');
        }
    }

    private function backupPath(string $filename): string
    {
        $disk = Storage::disk(config('system-health.backup_disk', 'local'));
        if (! method_exists($disk, 'path')) {
            throw new RuntimeException('The configured backup disk must be a local filesystem disk.');
        }

        return $disk->path('backups/'.$filename);
    }

    private function prune(): void
    {
        $days = max(1, (int) config('system-health.retention_days', 14));
        $old = DB::table('system_backups')->where('created_at', '<', now()->subDays($days))->get();

        foreach ($old as $backup) {
            Storage::disk($backup->disk)->delete('backups/'.$backup->filename);
            DB::table('system_backups')->where('id', $backup->id)->delete();
        }
    }
}
