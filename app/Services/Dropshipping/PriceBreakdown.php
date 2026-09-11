<?php

namespace App\Services\Dropshipping;

final readonly class PriceBreakdown
{
    public const STATUS_VALID = 'valid';
    public const STATUS_CONFLICT = 'conflict';
    public const STATUS_INVALID = 'invalid';

    /**
     * @param list<string> $warnings
     */
    public function __construct(
        public string $status,
        public ?float $costPrice,
        public ?float $minimumPrice,
        public ?float $targetSellingPrice,
        public ?float $finalPrice,
        public ?float $ceilingPrice,
        public ?string $reason = null,
        public array $warnings = [],
        public ?float $supplierMaximumPrice = null,
        public ?string $sellingBase = null,
        public ?string $sellingAdjustmentType = null,
        public ?float $sellingAdjustmentValue = null,
        public ?float $rawSellingPrice = null,
        public ?string $minimumBase = null,
        public ?string $minimumAdjustmentType = null,
        public ?float $minimumAdjustmentValue = null,
        public ?float $calculatedMinimumPrice = null,
        public bool $minimumCapEnabled = false,
        public ?string $maximumBase = null,
        public ?string $maximumAdjustmentType = null,
        public ?float $maximumAdjustmentValue = null,
        public ?float $calculatedMaximumPrice = null,
        public bool $maximumCapEnabled = false,
        public ?float $priceAfterMinimumCap = null,
        public ?float $priceAfterMaximumCap = null,
        public ?float $regularSellingPrice = null,
        public ?float $discountAmount = null,
        public ?float $finalSalePrice = null,
        public ?float $regularProfit = null,
        public ?float $saleProfit = null,
        public ?bool $pricingConflict = null,
        public ?string $conflictReason = null,
        public ?float $discountBasePrice = null,
        public ?float $requestedDiscountAmount = null,
        public ?float $priceAfterDiscount = null,
        public ?string $regularPriceSource = null,
    ) {
    }

    public function isValid(): bool
    {
        return $this->status === self::STATUS_VALID;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
