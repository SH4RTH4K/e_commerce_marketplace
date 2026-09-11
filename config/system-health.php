<?php

return [
    'backup_disk' => env('SYSTEM_HEALTH_BACKUP_DISK', 'local'),
    'retention_days' => (int) env('SYSTEM_HEALTH_BACKUP_RETENTION_DAYS', 14),
    'minimum_free_bytes' => (int) env('SYSTEM_HEALTH_MINIMUM_FREE_BYTES', 536870912),
    'max_restore_bytes' => (int) env('SYSTEM_HEALTH_MAX_RESTORE_BYTES', 524288000),
];
