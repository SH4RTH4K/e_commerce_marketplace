<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackupService;
use Illuminate\Console\Command;

class CreateStoreBackup extends Command
{
    protected $signature = 'store:backup';

    protected $description = 'Create a compressed private database backup';

    public function handle(DatabaseBackupService $backups): int
    {
        try {
            $id = $backups->create('scheduler');
            $this->info('Database backup completed: #'.$id);

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
