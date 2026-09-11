<?php

namespace App\Console\Commands;

use App\Models\DropshipSyncRun;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class DropshipPruneCommand extends Command
{
    protected $signature = 'dropship:prune
        {--days= : Retain completed runs for this many days (default: configured policy)}';

    protected $description = 'Prune old dropshipping sync-run history only';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('dropshipping.schedule.retention_days', 90));
        if ($days < 7) {
            $this->error('Retention must be at least 7 days.');

            return self::FAILURE;
        }

        $cutoff = Carbon::now()->subDays($days);
        $deleted = DropshipSyncRun::query()
            ->whereIn('status', ['completed', 'completed_with_errors', 'failed', 'cancelled'])
            ->whereNotNull('finished_at')
            ->where('finished_at', '<', $cutoff)
            ->delete();

        $this->info("Pruned {$deleted} completed sync run(s); local commerce data was not touched.");

        return self::SUCCESS;
    }
}
