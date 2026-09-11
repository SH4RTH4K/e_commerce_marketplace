<?php

namespace App\Services\Dropshipping\DTO;

use InvalidArgumentException;

final readonly class SupplierVariant
{
    public ?float $maximumPrice;

    /**
     * @param array<string, string> $attributes
     * @param array<string, mixed> $rawPayload
     */
    public function __construct(
        public string $id,
        public ?string $sku = null,
        public array $attributes = [],
        public ?float $costPrice = null,
        public ?float $maxPrice = null,
        public ?float $stockQuantity = null,
        public ?bool $isAvailable = null,
        public ?string $currency = null,
        public array $rawPayload = [],
    ) {
        $this->maximumPrice = $this->maxPrice;
        if (trim($this->id) === '') {
            throw new InvalidArgumentException('A supplier variant ID is required.');
        }
    }
}
