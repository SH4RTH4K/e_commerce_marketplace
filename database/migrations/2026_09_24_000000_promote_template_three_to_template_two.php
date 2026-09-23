<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $now = now();

        DB::table('settings')
            ->where('key', 'storefront_template')
            ->where('value', 'template-3')
            ->update(['value' => 'template-2', 'updated_at' => $now]);

        foreach ([
            'theme_typography_template_3' => 'theme_typography_template_2',
            'template_3_footer_config' => 'template_2_footer_config',
        ] as $legacyKey => $newKey) {
            $value = DB::table('settings')->where('key', $legacyKey)->value('value');

            if ($value !== null) {
                DB::table('settings')->updateOrInsert(
                    ['key' => $newKey],
                    ['value' => $value, 'updated_at' => $now]
                );
            }
        }

        DB::table('settings')->whereIn('key', [
            'theme_typography_template_3',
            'template_3_footer_config',
            'template_2_overview_featured_count',
            'template_2_overview_new_count',
            'template_2_overview_best_count',
        ])->delete();
    }

    public function down(): void
    {
        // The retired design is intentionally not restored on rollback.
    }
};
