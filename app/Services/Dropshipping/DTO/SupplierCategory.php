<?php

namespace App\Services\Dropshipping\DTO;

use InvalidArgumentException;

final readonly class SupplierCategory
{
    /** @param array<string, mixed> $rawPayload */
    public function __construct(
        public string $key,
        public string $name,
        public ?string $parentKey = null,
        public ?string $path = null,
        public ?int $level = null,
        public array $rawPayload = [],
    ) {
        if (trim($this->key) === '' || trim($this->name) === '') {
            throw new InvalidArgumentException('A supplier category requires a key and name.');
        }
        if ($this->level !== null && $this->level < 0) {
            throw new InvalidArgumentException('Supplier category level cannot be negative.');
        }
    }
}
