<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DropshippingSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_additive_dropshipping_schema_is_available(): void
    {
        foreach ([
            'dropship_driver_profiles',
            'dropship_suppliers',
            'dropship_supplier_categories',
            'dropship_category_mappings',
            'dropship_supplier_products',
            'dropship_supplier_variants',
            'dropship_product_links',
            'dropship_variant_links',
            'dropship_sync_runs',
            'dropship_sync_run_items',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Expected {$table} to exist.");
        }

        $this->assertTrue(Schema::hasColumns('dropship_driver_profiles', [
            'key', 'auth_key_header', 'auth_secret_header', 'products_path', 'field_mapping', 'is_active',
        ]));

        $this->assertTrue(Schema::hasColumns('dropship_suppliers', [
            'driver_key', 'api_key', 'secret_key', 'pricing_rules', 'sync_rules', 'price_field_mapping', 'deleted_at',
            'last_connection_status', 'last_connection_tested_at', 'capabilities',
        ]));
        $this->assertTrue(Schema::hasColumns('dropship_supplier_products', [
            'supplier_id', 'supplier_product_id', 'cost_price', 'max_price', 'stock_qty', 'raw_payload',
        ]));
        $this->assertTrue(Schema::hasColumns('dropship_sync_runs', [
            'supplier_id', 'type', 'status', 'processed_items', 'requested_by',
        ]));
        $this->assertTrue(Schema::hasColumns('dropship_product_links', ['pricing_snapshot']));
    }
}
