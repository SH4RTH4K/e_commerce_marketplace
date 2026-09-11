<?php

namespace App\Services\Dropshipping\DTO;

use InvalidArgumentException;

final readonly class SupplierProduct
{
    public ?float $maximumPrice;

    /**
     * @param list<string> $imageUrls
     * @param list<SupplierVariant> $variants
     * @param array<string, mixed> $rawPayload
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $currency,
        public ?string $productCode = null,
        public ?string $categoryKey = null,
        public ?float $costPrice = null,
        public ?float $maxPrice = null,
        public ?float $stockQuantity = null,
        public ?bool $isAvailable = null,
        public ?string $status = null,
        public ?string $brand = null,
        public ?string $description = null,
        public ?string $unit = null,
        public array $imageUrls = [],
        public array $variants = [],
        public array $rawPayload = [],
    ) {
        $this->maximumPrice = $this->maxPrice;
        if (trim($this->id) === '' || trim($this->name) === '') {
            throw new InvalidArgumentException('A supplier product requires an ID and name.');
        }
        if (! preg_match('/^[A-Z]{3}$/', $this->currency)) {
            throw new InvalidArgumentException('Supplier product currency must be an ISO 4217 code.');
        }
        foreach ($this->variants as $variant) {
            if (! $variant instanceof SupplierVariant) {
                throw new InvalidArgumentException('Supplier product variants must be SupplierVariant instances.');
            }
        }
    }

}
