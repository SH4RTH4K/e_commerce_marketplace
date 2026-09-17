<?php

use App\Support\ProductSlug;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('products')->select(['id', 'slug'])->orderBy('id')->chunkById(200, function ($products) {
            foreach ($products as $product) {
                DB::transaction(function () use ($product) {
                    $slug = ProductSlug::unique($product->slug, $product->id);
                    if ($slug === $product->slug) {
                        return;
                    }

                    DB::table('product_slug_aliases')->insertOrIgnore([
                        'product_id' => $product->id,
                        'slug' => $product->slug,
                    ]);
                    DB::table('products')->where('id', $product->id)->update(['slug' => $slug]);
                });
            }
        });

        Cache::forget('sitemap_urls');
    }

    public function down(): void
    {
        // Keep the now-public short URLs stable; the old URLs are retained as aliases.
    }
};
