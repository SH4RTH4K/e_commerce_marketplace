<?php

namespace App\Console\Commands;

use App\Services\OptimizedImageCleanupService;
use Illuminate\Console\Command;

class CleanupOptimizedImages extends Command
{
    protected $signature = 'storefront:cleanup-optimized-images
        {--grace-days=7 : Keep unreferenced variants newer than this many days}
        {--dry-run : Report orphaned files without moving them}';

    protected $description = 'Move orphaned generated storefront image variants to recovery trash';

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

        $result = $cleanup->quarantine($eligible);
        $this->info('Moved '.count($result['quarantined']).' orphaned optimized image(s) to recovery trash.');
        $this->line('Moved: '.count($result['quarantined']).'; protected current variants: '.$scan['protected_count'].'.');

        return self::SUCCESS;
    }
}
