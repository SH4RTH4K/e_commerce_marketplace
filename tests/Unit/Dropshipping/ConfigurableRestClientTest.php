<?php

namespace Tests\Unit\Dropshipping;

use App\Models\DropshipDriverProfile;
use App\Models\DropshipSupplier;
use App\Services\Dropshipping\Security\SupplierImageUrlValidator;
use App\Services\Dropshipping\Suppliers\ConfigurableRestClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ConfigurableRestClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_mapping_normalizes_a_product_page_without_executing_code(): void
    {
        DropshipDriverProfile::create([
            'key' => 'generic_rest', 'name' => 'Generic REST',
            'auth_key_header' => 'x-api-key', 'auth_secret_header' => 'x-secret',
            'products_path' => 'v1/products', 'collection_path' => 'data.items',
            'pagination_param' => 'page', 'default_currency' => 'BDT',
            'field_mapping' => ['fields' => ['id' => 'id', 'name' => 'title', 'stock_quantity' => 'stock']],
        ]);
        Http::fake(['https://supplier.example/*' => Http::response(['data' => ['items' => [['id' => 'p-1', 'title' => 'Mapped product', 'sale_price' => 490, 'price' => 690, 'stock' => 4]]]], 200)]);

        $supplier = new DropshipSupplier(['base_url' => 'https://supplier.example/api', 'api_key' => 'key', 'secret_key' => 'secret', 'price_field_mapping' => ['cost_field' => 'sale_price', 'maximum_field' => 'price']]);
        $validator = new SupplierImageUrlValidator(static fn (string $host): array => ['93.184.216.34']);
        $page = (new ConfigurableRestClient('generic_rest', $validator))->fetchProductsPage($supplier, 1);

        $this->assertSame('p-1', $page->items[0]->id);
        $this->assertSame('Mapped product', $page->items[0]->name);
        $this->assertSame(490.0, $page->items[0]->costPrice);
        $this->assertSame(690.0, $page->items[0]->maxPrice);
        $this->assertSame(4.0, $page->items[0]->stockQuantity);
        Http::assertSent(fn ($request) => $request->hasHeader('x-api-key', 'key') && $request->hasHeader('x-secret', 'secret'));
    }
}
