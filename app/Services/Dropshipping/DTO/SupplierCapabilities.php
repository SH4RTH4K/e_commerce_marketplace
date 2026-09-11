<?php

namespace App\Services\Dropshipping\DTO;

final readonly class SupplierCapabilities
{
    public function __construct(
        public bool $categories = false,
        public bool $nestedCategories = false,
        public bool $productDetail = false,
        public bool $variants = false,
        public bool $stock = false,
        public bool $images = false,
        public bool $supplierSearch = false,
        public bool $orderSubmission = false,
        public bool $tracking = false,
        public bool $cancellation = false,
    ) {
    }

    /** @return array<string, bool> */
    public function toArray(): array
    {
        return [
            'categories' => $this->categories,
            'nested_categories' => $this->nestedCategories,
            'product_detail' => $this->productDetail,
            'variants' => $this->variants,
            'stock' => $this->stock,
            'images' => $this->images,
            'supplier_search' => $this->supplierSearch,
            'order_submission' => $this->orderSubmission,
            'tracking' => $this->tracking,
            'cancellation' => $this->cancellation,
        ];
    }
}
