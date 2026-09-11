<?php

namespace App\Services\Dropshipping;

use InvalidArgumentException;

final readonly class PriceRule
{
    public const BASE_COST = 'supplier_cost';
    public const BASE_MAXIMUM = 'supplier_maximum';

    public const NONE = 'none';
    public const PLUS_FIXED = 'plus_fixed';
    public const MINUS_FIXED = 'minus_fixed';
    public const PLUS_PERCENT = 'plus_percent';
    public const MINUS_PERCENT = 'minus_percent';

    public function __construct(
        public string $base = self::BASE_COST,
        public string $adjustmentType = self::NONE,
        public float $adjustmentValue = 0.0,
    ) {
        if (! in_array($this->base, [self::BASE_COST, self::BASE_MAXIMUM], true)) {
            throw new InvalidArgumentException('Price rule base is invalid.');
        }
        if (! in_array($this->adjustmentType, [self::NONE, self::PLUS_FIXED, self::MINUS_FIXED, self::PLUS_PERCENT, self::MINUS_PERCENT], true)) {
            throw new InvalidArgumentException('Price rule adjustment is invalid.');
        }
        if (! is_finite($this->adjustmentValue) || $this->adjustmentValue < 0) {
            throw new InvalidArgumentException('Price rule adjustment must be non-negative.');
        }
    }

    /** @param array<string, mixed> $rule */
    public static function fromArray(array $rule): ?self
    {
        try {
            return new self(
                (string) ($rule['base'] ?? self::BASE_COST),
                (string) ($rule['adjustment_type'] ?? $rule['type'] ?? self::NONE),
                is_numeric($rule['adjustment_value'] ?? ($rule['value'] ?? 0)) ? (float) ($rule['adjustment_value'] ?? ($rule['value'] ?? 0)) : -1,
            );
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /** @return array{base:string,adjustment_type:string,adjustment_value:float} */
    public function toArray(): array
    {
        return [
            'base' => $this->base,
            'adjustment_type' => $this->adjustmentType,
            'adjustment_value' => $this->adjustmentValue,
        ];
    }
}
