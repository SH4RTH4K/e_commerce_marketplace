<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DatabaseBackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class SystemHealthController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/SystemHealth', [
            'health' => $this->health(),
            'migrations' => $this->migrationStatus(),
            'backups' => $this->backups(),
            'backupRetentionDays' => max(1, (int) config('system-health.retention_days', 14)),
        ]);
    }

    public function createBackup(DatabaseBackupService $backups)
    {
        return $this->runBackup($backups, 'create', 'Database backup');
    }

    public function createMediaBackup(DatabaseBackupService $backups)
    {
        return $this->runBackup($backups, 'createMedia', 'Media backup');
    }

    public function createFullBackup(DatabaseBackupService $backups)
    {
        return $this->runBackup($backups, 'createFull', 'Full backup');
    }

    public function restoreDatabase(Request $request, DatabaseBackupService $backups)
    {
        $file = $request->file('backup');
        $this->validateRestoreFile($request, 'backup', '/^ecommerce-\d{4}-\d{2}-\d{2}-\d{6}\.sql\.gz$/i');

        $safetyId = null;
        try {
            $safetyId = $backups->create($this->actorName('pre-restore'));
            $backups->restore($file->getRealPath());
            Cache::flush();

            return back()->with('status', 'Database restored successfully. Safety backup #'.$safetyId.' was created first.');
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('error', 'Database restore failed. Safety backup: '.($safetyId ?? 'unavailable').'. '.$exception->getMessage());
        }
    }

    public function restoreMedia(Request $request, DatabaseBackupService $backups)
    {
        $file = $request->file('media_backup');
        $this->validateRestoreFile($request, 'media_backup', '/^ecommerce-\d{4}-\d{2}-\d{2}-\d{6}\.media\.tar\.gz$/i');

        try {
            $backups->restoreMedia($file->getRealPath());
            Cache::flush();

            return back()->with('status', 'Media restore completed successfully.');
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('error', 'Media restore failed: '.$exception->getMessage());
        }
    }

    public function restoreFull(Request $request, DatabaseBackupService $backups)
    {
        $file = $request->file('full_backup');
        $this->validateRestoreFile($request, 'full_backup', '/^ecommerce-\d{4}-\d{2}-\d{2}-\d{6}\.full\.tar\.gz$/i');

        $safetyId = null;
        try {
            $safetyId = $backups->createFull($this->actorName('pre-full-restore'));
            $backups->restoreFull($file->getRealPath());
            Cache::flush();

            return back()->with('status', 'Full restore completed successfully. Safety backup #'.$safetyId.' was created first.');
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('error', 'Full restore failed. Safety backup: '.($safetyId ?? 'unavailable').'. '.$exception->getMessage());
        }
    }

    public function download(int $backup)
    {
        abort_unless(Schema::hasTable('system_backups'), 404);

        $record = DB::table('system_backups')
            ->where('id', $backup)
            ->where('status', 'completed')
            ->first();

        abort_unless($record, 404);

        $disk = Storage::disk($record->disk);
        abort_unless(method_exists($disk, 'path') && $disk->exists('backups/'.$record->filename), 404);

        return response()->download($disk->path('backups/'.$record->filename), $record->filename, [
            'Content-Type' => 'application/gzip',
        ]);
    }

    public function destroy(int $backup)
    {
        abort_unless(Schema::hasTable('system_backups'), 404);

        $record = DB::table('system_backups')->where('id', $backup)->first();
        if ($record) {
            Storage::disk($record->disk)->delete('backups/'.$record->filename);
            DB::table('system_backups')->where('id', $backup)->delete();
        }

        return back()->with('status', 'Backup deleted.');
    }

    public function clearCache()
    {
        try {
            Artisan::call('optimize:clear', ['--no-ansi' => true]);
            Artisan::call('view:cache', ['--no-ansi' => true]);

            return back()->with('status', 'Application caches cleared and compiled views rebuilt.');
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('error', 'Cache refresh failed: '.$exception->getMessage());
        }
    }

    public function runMigrations()
    {
        try {
            Artisan::call('migrate', ['--force' => true, '--no-ansi' => true]);
            $output = trim(Artisan::output());

            return back()->with('status', $output !== '' ? $output : 'Database migrations completed successfully.');
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('error', 'Migration failed: '.$exception->getMessage());
        }
    }

    private function health(): array
    {
        try {
            DB::select('SELECT 1');
            $database = ['ok' => true, 'message' => 'Connected'];
        } catch (\Throwable $exception) {
            $database = ['ok' => false, 'message' => Str::limit($exception->getMessage(), 180)];
        }

        $directories = [
            storage_path('app'),
            storage_path('framework'),
            storage_path('logs'),
        ];
        $storageWritable = collect($directories)->every(fn (string $directory) => is_dir($directory) && is_writable($directory));
        $environment = config('app.env').(config('app.debug') ? ' · debug ON' : ' · debug off');

        return [
            'database' => $database,
            'storage' => [
                'ok' => $storageWritable,
                'message' => $storageWritable ? 'Writable' : 'One or more runtime directories are not writable',
            ],
            'php' => [
                'ok' => version_compare(PHP_VERSION, '8.3.0', '>='),
                'message' => PHP_VERSION,
            ],
            'laravel' => [
                'ok' => true,
                'message' => app()->version(),
            ],
            'disk' => $this->diskHealth(),
            'environment' => [
                'ok' => config('app.env') === 'production' && ! config('app.debug'),
                'message' => $environment,
            ],
        ];
    }

    private function diskHealth(): array
    {
        $path = base_path();
        $free = @disk_free_space($path);
        $total = @disk_total_space($path);

        if ($free === false || $total === false || $total <= 0) {
            return ['ok' => false, 'message' => 'Unavailable'];
        }

        $minimum = max(1, (int) config('system-health.minimum_free_bytes', 536870912));
        $percent = round(((float) ($total - $free) / (float) $total) * 100, 1);

        return [
            'ok' => $free >= $minimum,
            'message' => $this->formatBytes((float) $free).' free of '.$this->formatBytes((float) $total).' ('.$percent.'% used)',
        ];
    }

    private function migrationStatus(): array
    {
        try {
            $migrator = app('migrator');
            $ran = array_flip($migrator->getRepository()->getRan());
            $files = $migrator->getMigrationFiles(database_path('migrations'));
            $pending = [];

            foreach ($files as $name => $path) {
                if (! isset($ran[$name])) {
                    $pending[] = $name;
                }
            }

            return [
                'ok' => $pending === [],
                'pending' => $pending,
                'ran' => count($files) - count($pending),
                'total' => count($files),
                'message' => $pending === []
                    ? 'All database migrations are up to date.'
                    : count($pending).' pending migration(s) require attention.',
            ];
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'pending' => [],
                'ran' => 0,
                'total' => 0,
                'message' => 'Unable to check migrations: '.Str::limit($exception->getMessage(), 180),
            ];
        }
    }

    private function backups(): array
    {
        try {
            if (! Schema::hasTable('system_backups')) {
                return [];
            }

            return DB::table('system_backups')
                ->latest()
                ->limit(30)
                ->get()
                ->map(fn ($backup) => [
                    'id' => $backup->id,
                    'filename' => $backup->filename,
                    'size_bytes' => (int) $backup->size_bytes,
                    'status' => $backup->status,
                    'created_by' => $backup->created_by,
                    'error' => $backup->error,
                    'created_at' => $backup->created_at,
                ])
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private function runBackup(DatabaseBackupService $service, string $method, string $label)
    {
        set_time_limit(0);

        try {
            $id = $service->{$method}($this->actorName());

            return back()->with('status', $label.' completed successfully. Reference #'.$id.'.');
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('error', $label.' failed: '.$exception->getMessage());
        }
    }

    private function validateRestoreFile(Request $request, string $field, string $filenamePattern): void
    {
        $request->validate([
            $field => [
                'required',
                'file',
                'max:'.(int) ceil(config('system-health.max_restore_bytes', 524288000) / 1024),
            ],
        ]);

        $file = $request->file($field);
        abort_unless($file && preg_match($filenamePattern, $file->getClientOriginalName()), 422, 'Please upload a backup generated by this application.');
    }

    private function actorName(string $prefix = ''): string
    {
        $name = auth()->user()?->name ?: 'admin';

        return trim($prefix.' '.$name);
    }

    private function formatBytes(float $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $index = 0;
        while ($bytes >= 1024 && $index < count($units) - 1) {
            $bytes /= 1024;
            $index++;
        }

        return number_format($bytes, $index === 0 ? 0 : 1).' '.$units[$index];
    }
}
