<?php

namespace App\Services\Dropshipping;

use App\Models\DropshipSupplier;
use App\Models\DropshipSyncRun;
use App\Models\DropshipSyncRunItem;
use App\Models\User;
use App\Services\Dropshipping\Support\SyncRunState;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SyncRunService
{
    public function createRun(
        DropshipSupplier $supplier,
        string $type,
        ?User $requestedBy = null,
        array $filters = [],
    ): DropshipSyncRun {
        $type = trim($type);
        if ($type === '') {
            throw new InvalidArgumentException('A sync run type is required.');
        }

        return DropshipSyncRun::create([
            'supplier_id' => $supplier->id,
            'type' => $type,
            'status' => SyncRunState::QUEUED,
            'filters' => $filters,
            'requested_by' => $requestedBy?->id,
        ]);
    }

    /**
     * Queues unique keys only. A retried dispatch cannot produce a second run item.
     *
     * @param list<string> $itemKeys
     */
    public function queueItems(DropshipSyncRun $syncRun, array $itemKeys): int
    {
        $keys = array_values(array_unique(array_filter(array_map(
            static fn (mixed $key): string => is_string($key) ? trim($key) : '',
            $itemKeys,
        ))));

        return DB::transaction(function () use ($syncRun, $keys): int {
            $run = DropshipSyncRun::query()->lockForUpdate()->findOrFail($syncRun->id);
            if (! in_array($run->status, [SyncRunState::QUEUED, SyncRunState::RUNNING], true)) {
                return 0;
            }

            $now = now();
            $created = $keys === [] ? 0 : DB::table('dropship_sync_run_items')->insertOrIgnore(
                array_map(static fn (string $key): array => [
                    'sync_run_id' => $run->id,
                    'item_key' => $key,
                    'status' => SyncRunState::ITEM_QUEUED,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $keys),
            );

            $run->update(['total_items' => $run->items()->count()]);

            return $created;
        });
    }

    public function start(DropshipSyncRun $syncRun): bool
    {
        return DropshipSyncRun::query()
            ->whereKey($syncRun->id)
            ->where('status', SyncRunState::QUEUED)
            ->update([
                'status' => SyncRunState::RUNNING,
                'started_at' => now(),
            ]) === 1;
    }

    public function cancel(DropshipSyncRun $syncRun): bool
    {
        return DropshipSyncRun::query()
            ->whereKey($syncRun->id)
            ->whereIn('status', [SyncRunState::QUEUED, SyncRunState::RUNNING, SyncRunState::PAUSED])
            ->update([
                'status' => SyncRunState::CANCELLED,
                'finished_at' => now(),
            ]) === 1;
    }

    public function failRun(DropshipSyncRun $syncRun, string $errorSummary): bool
    {
        return DropshipSyncRun::query()
            ->whereKey($syncRun->id)
            ->whereIn('status', [SyncRunState::QUEUED, SyncRunState::RUNNING])
            ->update([
                'status' => SyncRunState::FAILED,
                'error_summary' => trim($errorSummary),
                'finished_at' => now(),
            ]) === 1;
    }

    /** Returns false for a duplicate, cancelled, or already-finished item. */
    public function startItem(DropshipSyncRun $syncRun, string $itemKey): bool
    {
        return DB::transaction(function () use ($syncRun, $itemKey): bool {
            $run = DropshipSyncRun::query()->lockForUpdate()->findOrFail($syncRun->id);
            $item = $run->items()->where('item_key', trim($itemKey))->lockForUpdate()->firstOrFail();

            if (! SyncRunState::canStartItem($run->status, $item->status)) {
                return false;
            }

            $item->update([
                'status' => SyncRunState::ITEM_RUNNING,
                'started_at' => now(),
                'error_summary' => null,
            ]);

            return true;
        });
    }

    public function succeedItem(DropshipSyncRun $syncRun, string $itemKey): bool
    {
        return $this->finishItem($syncRun, $itemKey, SyncRunState::ITEM_SUCCEEDED);
    }

    public function failItem(DropshipSyncRun $syncRun, string $itemKey, string $errorSummary): bool
    {
        return $this->finishItem($syncRun, $itemKey, SyncRunState::ITEM_FAILED, $errorSummary);
    }

    /** Makes a failed attempt eligible for the queue's next retry. */
    public function requeueItem(DropshipSyncRun $syncRun, string $itemKey): bool
    {
        return DB::transaction(function () use ($syncRun, $itemKey): bool {
            $run = DropshipSyncRun::query()->lockForUpdate()->findOrFail($syncRun->id);
            $item = $run->items()->where('item_key', trim($itemKey))->lockForUpdate()->firstOrFail();

            if ($run->status !== SyncRunState::RUNNING || $item->status !== SyncRunState::ITEM_RUNNING) {
                return false;
            }

            $item->update([
                'status' => SyncRunState::ITEM_QUEUED,
                'started_at' => null,
            ]);

            return true;
        });
    }

    /** Marks an item failed after the queue has exhausted its retries. */
    public function failPendingItem(DropshipSyncRun $syncRun, string $itemKey, string $errorSummary): bool
    {
        return $this->finishItem(
            $syncRun,
            $itemKey,
            SyncRunState::ITEM_FAILED,
            $errorSummary,
            [SyncRunState::ITEM_QUEUED, SyncRunState::ITEM_RUNNING],
        );
    }

    public function finalize(DropshipSyncRun $syncRun): ?DropshipSyncRun
    {
        return DB::transaction(function () use ($syncRun): ?DropshipSyncRun {
            $run = DropshipSyncRun::query()->lockForUpdate()->findOrFail($syncRun->id);
            if (SyncRunState::isTerminal($run->status)) {
                return $run;
            }

            if ($run->items()->whereIn('status', [SyncRunState::ITEM_QUEUED, SyncRunState::ITEM_RUNNING])->exists()) {
                return null;
            }

            $status = $run->failed_items > 0
                ? SyncRunState::COMPLETED_WITH_ERRORS
                : SyncRunState::COMPLETED;
            $run->update([
                'status' => $status,
                'finished_at' => now(),
            ]);

            return $run->refresh();
        });
    }

    private function finishItem(
        DropshipSyncRun $syncRun,
        string $itemKey,
        string $status,
        ?string $errorSummary = null,
        array $allowedCurrentStates = [SyncRunState::ITEM_RUNNING],
    ): bool {
        return DB::transaction(function () use ($syncRun, $itemKey, $status, $errorSummary, $allowedCurrentStates): bool {
            $run = DropshipSyncRun::query()->lockForUpdate()->findOrFail($syncRun->id);
            $item = $run->items()->where('item_key', trim($itemKey))->lockForUpdate()->firstOrFail();

            if (! in_array($item->status, $allowedCurrentStates, true)) {
                return false;
            }

            $item->update([
                'status' => $status,
                'error_summary' => $status === SyncRunState::ITEM_FAILED ? trim($errorSummary ?? '') : null,
                'finished_at' => now(),
            ]);

            $counters = [
                'processed_items' => $run->processed_items + 1,
                $status === SyncRunState::ITEM_SUCCEEDED ? 'success_items' : 'failed_items'
                    => ($status === SyncRunState::ITEM_SUCCEEDED ? $run->success_items : $run->failed_items) + 1,
            ];
            $run->update($counters);

            return true;
        });
    }
}
