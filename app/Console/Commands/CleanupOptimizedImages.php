<?php

namespace App\Console\Commands;

use App\Services\OptimizedImageCleanupService;
use Illuminate\Console\Command;

class CleanupOptimizedImages extends Command
{
    protected $signature = 'storefront:cleanup-optimized-images
        {--grace-days=7 : Keep unreferenced variants newer than this many days}
        {--dry-run : Report orphaned files without deleting them}';

    protected $description = 'Remove orphaned generated storefront image variants';

    public function handle(OptimizedImageCleanupService $cleanup): int
    {
        $graceDays = max(0, (int) $this->option('grace-days'));
        try {
            $scan = $cleanup->scan();
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        $eligible = collect($scan['files'])
            ->filter(fn (array $file): bool => $file['age_days'] >= $graceDays)
            ->pluck('filename')
            ->all();

        if ($this->option('dry-run')) {
            $this->info('Found '.count($eligible).' orphaned optimized image(s) past the grace period.');

            return self::SUCCESS;
        }

        $result = $cleanup->delete($eligible);
        $this->info('Removed '.count($result['deleted']).' orphaned optimized image(s).');
        $this->line('Deleted: '.count($result['deleted']).'; protected current variants: '.$scan['protected_count'].'.');

        return self::SUCCESS;
    }
}
