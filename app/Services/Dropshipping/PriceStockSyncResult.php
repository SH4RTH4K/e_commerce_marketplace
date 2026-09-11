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
}
