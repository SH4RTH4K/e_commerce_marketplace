<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductSlug
{
    public const MAX_LENGTH = 55;

    public static function compact(string $value): string
    {
        $slug = Str::slug($value);
        // Old supplier imports appended an internal ID and random token.
        $slug = preg_replace('/-ds-\d+-[a-z0-9]+$/', '', $slug) ?? $slug;
        $words = array_slice(explode('-', $slug), 0, 7);

        return self::fit(implode('-', $words), self::MAX_LENGTH) ?: 'product';
    }

    public static function unique(string $value, ?int $ignoreId = null): string
    {
        $base = self::compact($value);
        $slug = $base;
        $suffix = 2;

        // Historical URLs remain reserved, so a new product cannot steal a shared link.
        while (self::isTaken($slug, $ignoreId)) {
            $ending = '-'.$suffix++;
            $slug = self::fit($base, self::MAX_LENGTH - strlen($ending)).$ending;
        }

        return $slug;
    }

    private static function isTaken(string $slug, ?int $ignoreId): bool
    {
        return DB::table('products')->where('slug', $slug)
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists()
            || DB::table('product_slug_aliases')->where('slug', $slug)
                ->when($ignoreId !== null, fn ($query) => $query->where('product_id', '!=', $ignoreId))
                ->exists();
    }

    private static function fit(string $slug, int $length): string
    {
        if (Str::length($slug) <= $length) {
            return $slug;
        }

        $short = Str::substr($slug, 0, $length);
        if (Str::substr($slug, $length, 1) !== '-' && str_contains($short, '-')) {
            $short = substr($short, 0, strrpos($short, '-'));
        }

        return rtrim($short, '-');
    }
}
