<?php

namespace App\Services\Dropshipping\Contracts;

use App\Models\DropshipSupplier;
use App\Services\Dropshipping\DTO\SupplierCapabilities;
use App\Services\Dropshipping\DTO\SupplierCategory;
use App\Services\Dropshipping\DTO\SupplierConnectionResult;
use App\Services\Dropshipping\DTO\SupplierProduct;
use App\Services\Dropshipping\DTO\SupplierProductPage;

interface SupplierClient
{
    /** A stable, application-owned key; never an administrator-provided class name. */
    public function driverKey(): string;

    public function capabilities(): SupplierCapabilities;

    public function testConnection(DropshipSupplier $supplier): SupplierConnectionResult;

    /**
     * @param array<string, scalar|null> $filters
     */
    public function fetchProductsPage(
        DropshipSupplier $supplier,
        int $page,
        array $filters = [],
        bool $bypassCache = false,
    ): SupplierProductPage;

    public function fetchProduct(DropshipSupplier $supplier, string $supplierProductId): ?SupplierProduct;

    /** @return list<SupplierCategory> */
    public function fetchCategories(DropshipSupplier $supplier, bool $bypassCache = false): array;
}
