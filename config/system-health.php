<?php

return [
    'backup_disk' => env('SYSTEM_HEALTH_BACKUP_DISK', 'local'),
    'retention_days' => (int) env('SYSTEM_HEALTH_BACKUP_RETENTION_DAYS', 14),
    'minimum_free_bytes' => (int) env('SYSTEM_HEALTH_MINIMUM_FREE_BYTES', 536870912),
    'max_restore_bytes' => (int) env('SYSTEM_HEALTH_MAX_RESTORE_BYTES', 524288000),
    // These tables contain rebuildable runtime state, not store records. Keeping
    // them out of backups prevents a large cache from turning a small backup
    // into a slow, multi-hundred-megabyte web request.
    'backup_excluded_tables' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('SYSTEM_HEALTH_BACKUP_EXCLUDED_TABLES', 'cache,cache_locks,sessions,jobs,failed_jobs,job_batches')),
    ))),
    'backup_compression_level' => min(9, max(1, (int) env('SYSTEM_HEALTH_BACKUP_COMPRESSION_LEVEL', 6))),
];
