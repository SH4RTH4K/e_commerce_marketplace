<?php

namespace App\Services\Dropshipping\DTO;

use InvalidArgumentException;

final readonly class SupplierProductPage
{
    /** @param list<SupplierProduct> $items */
    public function __construct(
        public array $items,
        public int $currentPage,
        public ?int $lastPage = null,
        public ?int $nextPage = null,
    ) {
        if ($this->currentPage < 1) {
            throw new InvalidArgumentException('Current page must be at least 1.');
        }
        if ($this->lastPage !== null && $this->lastPage < $this->currentPage) {
            throw new InvalidArgumentException('Last page cannot precede current page.');
        }
        if ($this->nextPage !== null && $this->nextPage <= $this->currentPage) {
            throw new InvalidArgumentException('Next page must advance pagination.');
        }
        if ($this->lastPage !== null && $this->nextPage !== null && $this->nextPage > $this->lastPage) {
            throw new InvalidArgumentException('Next page cannot exceed the reported last page.');
        }
        foreach ($this->items as $item) {
            if (! $item instanceof SupplierProduct) {
                throw new InvalidArgumentException('Supplier product pages must contain SupplierProduct instances.');
            }
        }
    }

    public function hasNextPage(): bool
    {
        if ($this->nextPage !== null) {
            return true;
        }

        return $this->lastPage !== null && $this->currentPage < $this->lastPage;
    }
}
