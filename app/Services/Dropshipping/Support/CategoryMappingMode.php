<?php

namespace App\Services\Dropshipping\Support;

final class CategoryMappingMode
{
    public const MANUAL = 'manual';
    public const AUTO = 'auto';

    public static function isValid(string $mode): bool
    {
        return in_array($mode, [self::MANUAL, self::AUTO], true);
    }
}
