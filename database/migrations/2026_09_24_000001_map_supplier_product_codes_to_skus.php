<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dropship_driver_profiles')) {
            $profile = DB::table('dropship_driver_profiles')->where('key', 'mohasagor')->first();

            if ($profile) {
                $mapping = json_decode((string) $profile->field_mapping, true);
                $mapping = is_array($mapping) ? $mapping : [];
                $mapping['fields'] = is_array($mapping['fields'] ?? null) ? $mapping['fields'] : [];
                $mapping['fields']['product_code'] = 'product_code';

                DB::table('dropship_driver_profiles')->where('id', $profile->id)->update([
                    'field_mapping' => json_encode($mapping, JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
            }
        }

        if (! Schema::hasTable('dropship_supplier_products') || ! Schema::hasTable('dropship_product_links')) {
            return;
        }

        DB::table('dropship_supplier_products')
            ->select(['id', 'product_code', 'raw_payload'])
            ->orderBy('id')
            ->each(function (object $source): void {
                $payload = json_decode((string) $source->raw_payload, true);
                $code = is_array($payload) ? trim((string) ($payload['product_code'] ?? '')) : '';
                $code = $code !== '' ? $code : trim((string) ($source->product_code ?? ''));

                if ($code === '') {
                    return;
                }

                if ((string) $source->product_code !== $code) {
                    DB::table('dropship_supplier_products')->where('id', $source->id)->update([
                        'product_code' => $code,
                        'updated_at' => now(),
                    ]);
                }

                $productIds = DB::table('dropship_product_links')
                    ->where('supplier_product_row_id', $source->id)
                    ->pluck('product_id');

                if ($productIds->isNotEmpty()) {
                    DB::table('products')->whereIn('id', $productIds)->update([
                        'sku' => $code,
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Supplier product codes are authoritative and intentionally retained.
    }
};
