<?php

namespace App\Services\Dropshipping;

use InvalidArgumentException;

final class PriceAdjustmentCalculator
{
    /**
     * Calculate in integer minor units. Percentages are represented in basis
     * points so formula arithmetic does not depend on binary floating point.
     *
     * @return array{price:int,base:int,adjustment:int}
     */
    public function calculate(?int $costCents, ?int $maximumCents, PriceRule $rule): array
    {
        $base = $rule->base === PriceRule::BASE_MAXIMUM ? $maximumCents : $costCents;
        if ($base === null) {
            throw new InvalidArgumentException($rule->base === PriceRule::BASE_MAXIMUM
                ? 'Supplier Maximum Price is missing for this product.'
                : 'Supplier Cost Price is missing for this product.');
        }

        $valueCents = (int) round($rule->adjustmentValue * 100);
        $adjustment = match ($rule->adjustmentType) {
            PriceRule::NONE => 0,
            PriceRule::PLUS_FIXED, PriceRule::MINUS_FIXED => $valueCents,
            PriceRule::PLUS_PERCENT, PriceRule::MINUS_PERCENT => $this->percentage($base, $rule->adjustmentValue),
            default => throw new InvalidArgumentException('Price rule adjustment is invalid.'),
        };

        $price = match ($rule->adjustmentType) {
            PriceRule::MINUS_FIXED, PriceRule::MINUS_PERCENT => $base - $adjustment,
            default => $base + $adjustment,
        };
        if ($price < 0) {
            throw new InvalidArgumentException('Calculated price cannot be negative.');
        }

        return ['price' => $price, 'base' => $base, 'adjustment' => $adjustment];
    }

    private function percentage(int $baseCents, float $percentage): int
    {
        $basisPoints = (int) round($percentage * 100);

        return intdiv(($baseCents * $basisPoints) + 5000, 10000);
    }
}
