<?php

namespace App\Services\Dropshipping;

use App\Models\Product;

final readonly class ProductImportResult
{
    public const IMPORTED = 'imported';
    public const ALREADY_LINKED = 'already_linked';
    public const BLOCKED = 'blocked';

    public function __construct(
        public string $status,
        public ?Product $product = null,
        public ?string $reason = null,
    ) {
    }

    public function wasImported(): bool
    {
        return $this->status === self::IMPORTED;
    }
}
