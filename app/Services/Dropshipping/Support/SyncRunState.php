<?php

namespace App\Services\Dropshipping\Support;

final class SyncRunState
{
    public const QUEUED = 'queued';
    public const RUNNING = 'running';
    public const PAUSED = 'paused';
    public const COMPLETED = 'completed';
    public const COMPLETED_WITH_ERRORS = 'completed_with_errors';
    public const FAILED = 'failed';
    public const CANCELLED = 'cancelled';

    public const ITEM_QUEUED = 'queued';
    public const ITEM_RUNNING = 'running';
    public const ITEM_SUCCEEDED = 'succeeded';
    public const ITEM_FAILED = 'failed';

    public static function canStart(string $status): bool
    {
        return $status === self::QUEUED;
    }

    public static function canCancel(string $status): bool
    {
        return in_array($status, [self::QUEUED, self::RUNNING, self::PAUSED], true);
    }

    public static function isTerminal(string $status): bool
    {
        return in_array($status, [self::COMPLETED, self::COMPLETED_WITH_ERRORS, self::FAILED, self::CANCELLED], true);
    }

    public static function canStartItem(string $runStatus, string $itemStatus): bool
    {
        return $runStatus === self::RUNNING && $itemStatus === self::ITEM_QUEUED;
    }

    public static function isTerminalItem(string $status): bool
    {
        return in_array($status, [self::ITEM_SUCCEEDED, self::ITEM_FAILED], true);
    }
}
