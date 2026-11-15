<?php

namespace App\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseCacheService
{
    public function stats(): array
    {
        $table = $this->table();
        $connection = $this->connection();

        if (! Schema::connection($connection)->hasTable($table)) {
            return [
                'available' => false,
                'active' => $this->isActive(),
                'table' => $table,
                'driver' => 'database',
                'message' => "The {$table} table is unavailable.",
            ];
        }

        try {
            $database = DB::connection($connection);
            $totalRows = (int) $database->table($table)->count();
            $expiredRows = (int) $database->table($table)
                ->where('expiration', '<=', now()->timestamp)
                ->count();
            $bytes = $this->allocatedBytes($database, $table);

            return [
                'available' => true,
                'active' => $this->isActive(),
                'table' => $table,
                'driver' => 'database',
                'total_rows' => $totalRows,
                'active_rows' => max(0, $totalRows - $expiredRows),
                'expired_rows' => $expiredRows,
                'allocated_bytes' => $bytes,
                'allocated_formatted' => $this->formatBytes($bytes),
                'message' => $expiredRows > 0
                    ? number_format($expiredRows).' expired row(s) can be removed safely.'
                    : 'No expired cache rows need cleanup.',
            ];
        } catch (\Throwable $exception) {
            report($exception);

            return [
                'available' => false,
                'active' => $this->isActive(),
                'table' => $table,
                'driver' => 'database',
                'message' => 'Unable to inspect the database cache.',
            ];
        }
    }

    public function pruneExpired(): int
    {
        if (! Schema::connection($this->connection())->hasTable($this->table())) {
            return 0;
        }

        return DB::connection($this->connection())
            ->table($this->table())
            ->where('expiration', '<=', now()->timestamp)
            ->delete();
    }

    public function clear(): int
    {
        if (! Schema::connection($this->connection())->hasTable($this->table())) {
            return 0;
        }

        $database = DB::connection($this->connection());
        $rows = (int) $database->table($this->table())->count();

        // TRUNCATE releases MySQL's allocated table space. A regular cache
        // flush only deletes rows and can leave a very large .ibd allocation.
        $database->table($this->table())->truncate();

        return $rows;
    }

    private function allocatedBytes(ConnectionInterface $database, string $table): int
    {
        $driver = $database->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $record = $database->selectOne(
                'SELECT COALESCE(DATA_LENGTH, 0) + COALESCE(INDEX_LENGTH, 0) AS aggregate
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
                [$database->getDatabaseName(), $table],
            );

            return max(0, (int) ($record->aggregate ?? 0));
        }

        // Portable approximation for local/test databases. Production MySQL
        // uses the actual allocated table and index bytes above.
        $record = $database->table($table)
            ->selectRaw('COALESCE(SUM(LENGTH("key") + LENGTH("value") + 8), 0) AS aggregate')
            ->first();

        return max(0, (int) ($record->aggregate ?? 0));
    }

    private function isActive(): bool
    {
        $defaultStore = (string) config('cache.default', 'database');
        $pageStore = (string) (config('storefront.page_cache.store') ?: $defaultStore);

        return config("cache.stores.{$defaultStore}.driver") === 'database'
            || config("cache.stores.{$pageStore}.driver") === 'database';
    }

    private function connection(): ?string
    {
        return config('cache.stores.database.connection');
    }

    private function table(): string
    {
        return (string) config('cache.stores.database.table', 'cache');
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $amount = (float) max(0, $bytes);
        $index = 0;

        while ($amount >= 1024 && $index < count($units) - 1) {
            $amount /= 1024;
            $index++;
        }

        return number_format($amount, $index === 0 ? 0 : 1).' '.$units[$index];
    }
}
