<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $profile = DB::table('dropship_driver_profiles')->where('key', 'mohasagor')->first();
        if (! $profile) return;

        $mapping = json_decode((string) $profile->field_mapping, true);
        if (! is_array($mapping) || isset($mapping['variant_collection'])) return;

        $mapping['variant_collection'] = 'product_variants';
        $mapping['variant_fields'] = [
            'id' => 'id',
            'attributes' => [
                'type' => 'attribute',
                'value' => 'variant',
            ],
        ];

        DB::table('dropship_driver_profiles')->where('id', $profile->id)->update([
            'field_mapping' => json_encode($mapping, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $profile = DB::table('dropship_driver_profiles')->where('key', 'mohasagor')->first();
        if (! $profile) return;

        $mapping = json_decode((string) $profile->field_mapping, true);
        if (! is_array($mapping)) return;

        unset($mapping['variant_collection'], $mapping['variant_fields']);
        DB::table('dropship_driver_profiles')->where('id', $profile->id)->update([
            'field_mapping' => json_encode($mapping, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
    }
};