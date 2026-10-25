<?php

namespace App\Console\Commands;

use App\Services\OptimizedImageCleanupService;
use Illuminate\Console\Command;

class PurgeOptimizedImageTrash extends Command
{
    protected $signature = 'storefront:purge-optimized-image-trash
        {--retention-days= : Permanently remove recovery batches older than this many days}';

    protected $description = 'Permanently remove optimized image recovery batches after their retention period';

    public function handle(OptimizedImageCleanupService $cleanup): int
    {
        $retentionDays = $this->option('retention-days');
        $retentionDays = $retentionDays === null
            ? null
            : max(1, (int) $retentionDays);
        $result = $cleanup->purgeTrash($retentionDays);

        $this->info('Permanently removed '.$result['purged'].' recovery batch(es) older than '.($retentionDays ?? config('optimized-images.trash_retention_days', 30)).' days.');
        $this->line('Freed '.$result['formatted'].' from optimized image recovery trash.');

        return self::SUCCESS;
    }
}
