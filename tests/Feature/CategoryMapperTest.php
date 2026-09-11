<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DropshipSupplier;
use App\Services\Dropshipping\CategoryMapper;
use App\Services\Dropshipping\Support\CategoryMappingMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryMapperTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_mapping_is_manual_supplier_scoped_and_can_be_replaced(): void
    {
        $supplier = DropshipSupplier::create([
            'key' => 'first-supplier',
            'name' => 'First Supplier',
            'driver_key' => 'fake',
            'base_url' => 'https://supplier.example',
        ]);
        $otherSupplier = DropshipSupplier::create([
            'key' => 'second-supplier',
            'name' => 'Second Supplier',
            'driver_key' => 'fake',
            'base_url' => 'https://other-supplier.example',
        ]);
        $firstCategory = Category::create(['name' => 'First', 'slug' => 'first']);
        $replacementCategory = Category::create(['name' => 'Replacement', 'slug' => 'replacement']);
        $mapper = app(CategoryMapper::class);

        $mapping = $mapper->mapManually($supplier, 'electronics', $firstCategory);

        $this->assertSame(CategoryMappingMode::MANUAL, $mapping->mapping_mode);
        $this->assertTrue($mapper->localCategoryFor($supplier, 'electronics')->is($firstCategory));
        $this->assertNull($mapper->localCategoryFor($otherSupplier, 'electronics'));

        $mapper->mapManually($supplier, 'electronics', $replacementCategory);

        $this->assertTrue($mapper->localCategoryFor($supplier, 'electronics')->is($replacementCategory));
        $this->assertTrue($mapper->unmap($supplier, 'electronics'));
        $this->assertNull($mapper->localCategoryFor($supplier, 'electronics'));
    }
}
