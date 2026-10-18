<?php

namespace App\Services\Dropshipping;

use App\Models\Product;

final readonly class PriceStockSyncResult
{
    public const UPDATED = 'updated';
    public const PARTIAL = 'partial';
    public const SKIPPED = 'skipped';

    /** @param list<string> $warnings */
    public function __construct(
        public string $status,
        public ?Product $product = null,
        public bool $priceUpdated = false,
        public bool $stockUpdated = false,
        public array $warnings = [],
        public bool $descriptionUpdated = false,
    ) {
    }

    public function ownershipBlocked(): bool
    {
        return array_intersect($this->warnings, ['missing_supplier_connection', 'inactive_supplier', 'manual_product_protected', 'supplier_ownership_conflict', 'ambiguous_supplier_mapping']) !== [];
    }
}
