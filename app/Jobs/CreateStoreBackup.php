<?php

namespace App\Jobs;

use App\Services\DatabaseBackupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class CreateStoreBackup implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 0;

    public function __construct(public int $backupId, public string $type)
    {
    }

    public function handle(DatabaseBackupService $backups): void
    {
        $backups->runQueued($this->backupId, $this->type);
    }

    public function failed(Throwable $exception): void
    {
        app(DatabaseBackupService::class)->failQueued($this->backupId, $exception->getMessage());
    }
}
