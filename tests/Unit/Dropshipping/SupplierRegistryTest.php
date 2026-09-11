<?php

namespace Tests\Unit\Dropshipping;

use App\Models\DropshipSupplier;
use App\Services\Dropshipping\Contracts\SupplierClient;
use App\Services\Dropshipping\DTO\SupplierCapabilities;
use App\Services\Dropshipping\DTO\SupplierCategory;
use App\Services\Dropshipping\DTO\SupplierConnectionResult;
use App\Services\Dropshipping\DTO\SupplierProduct;
use App\Services\Dropshipping\DTO\SupplierProductPage;
use App\Services\Dropshipping\SupplierRegistry;
use LogicException;
use PHPUnit\Framework\TestCase;

class SupplierRegistryTest extends TestCase
{
    public function test_it_resolves_only_application_registered_driver_keys(): void
    {
        $client = new FakeSupplierClient('fake_supplier');
        $registry = new SupplierRegistry([$client]);

        $this->assertTrue($registry->hasDriver('fake_supplier'));
        $this->assertSame(['fake_supplier'], $registry->driverKeys());
        $this->assertSame($client, $registry->forDriver('fake_supplier'));
    }

    public function test_it_rejects_unknown_and_duplicate_driver_keys(): void
    {
        $registry = new SupplierRegistry([new FakeSupplierClient('fake_supplier')]);

        try {
            $registry->forDriver('administrator_supplied_class_name');
            $this->fail('Expected an unknown driver to be rejected.');
        } catch (LogicException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(LogicException::class);
        $registry->register(new FakeSupplierClient('fake_supplier'));
    }

    public function test_product_page_rejects_non_advancing_pagination(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new SupplierProductPage([], currentPage: 2, nextPage: 2);
    }
}

class FakeSupplierClient implements SupplierClient
{
    public function __construct(private readonly string $key)
    {
    }

    public function driverKey(): string
    {
        return $this->key;
    }

    public function capabilities(): SupplierCapabilities
    {
        return new SupplierCapabilities();
    }

    public function testConnection(DropshipSupplier $supplier): SupplierConnectionResult
    {
        return new SupplierConnectionResult(true, 'Fake connection.');
    }

    public function fetchProductsPage(DropshipSupplier $supplier, int $page, array $filters = [], bool $bypassCache = false): SupplierProductPage
    {
        return new SupplierProductPage([], $page);
    }

    public function fetchProduct(DropshipSupplier $supplier, string $supplierProductId): ?SupplierProduct
    {
        return null;
    }

    public function fetchCategories(DropshipSupplier $supplier, bool $bypassCache = false): array
    {
        return [];
    }
}
