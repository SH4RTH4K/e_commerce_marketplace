<?php

use App\Services\SitemapService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SLUG_MAP = [
        'watch' => ['watches', 'Watches'],
        'airpod' => ['airpods', 'AirPods'],
        'others' => ['miscellaneous-products', 'Miscellaneous Products'],
    ];

    public function up(): void
    {
        foreach (self::SLUG_MAP as $oldSlug => [$newSlug, $newName]) {
            $category = DB::table('categories')->where('slug', $oldSlug)->first(['id']);
            if (! $category) {
                continue;
            }

            if (DB::table('categories')->where('slug', $newSlug)->where('id', '!=', $category->id)->exists()) {
                throw new RuntimeException("Cannot rename category {$oldSlug}: {$newSlug} already exists.");
            }

            DB::table('categories')->where('id', $category->id)->update([
                'slug' => $newSlug,
                'name' => $newName,
                'updated_at' => now(),
            ]);
        }

        app(SitemapService::class)->invalidate();
    }

    public function down(): void
    {
        foreach (self::SLUG_MAP as $oldSlug => [$newSlug, $oldName]) {
            $category = DB::table('categories')->where('slug', $newSlug)->first(['id']);
            if (! $category) {
                continue;
            }

            if (DB::table('categories')->where('slug', $oldSlug)->where('id', '!=', $category->id)->exists()) {
                throw new RuntimeException("Cannot restore category {$newSlug}: {$oldSlug} already exists.");
            }

            DB::table('categories')->where('id', $category->id)->update([
                'slug' => $oldSlug,
                'name' => $oldName,
                'updated_at' => now(),
            ]);
        }

        app(SitemapService::class)->invalidate();
    }
};
