<?php

namespace App\Services\Dropshipping;

use InvalidArgumentException;

/** Supplier-independent pricing engine. Supplier adapters provide normalized cost and maximum values. */
class PricingEngine
{
    public function __construct(private readonly PriceAdjustmentCalculator $calculator = new PriceAdjustmentCalculator())
    {
    }

    public function calculate(mixed $cost, mixed $supplierMaximum = null, array $rules = []): PriceBreakdown
    {
        if (! $this->isStructured($rules)) {
            return $this->calculateLegacy($cost, $supplierMaximum, $rules);
        }

        $costCents = $this->toCents($cost);
        $maximumCents = $this->toCents($supplierMaximum);
        $selling = PriceRule::fromArray(is_array($rules['selling'] ?? null) ? $rules['selling'] : []);
        $minimum = PriceRule::fromArray(is_array($rules['minimum'] ?? null) ? $rules['minimum'] : []);
        $maximumRule = is_array($rules['maximum'] ?? null) ? $rules['maximum'] : [];
        $maximum = PriceRule::fromArray(['base' => PriceRule::BASE_MAXIMUM, ...$maximumRule]);
        if ($selling === null || $minimum === null || $maximum === null) {
            return $this->invalid('invalid_price_formula', $costCents, $maximumCents);
        }

        try {
            $sellingResult = $this->calculator->calculate($costCents, $maximumCents, $selling);
            $minimumResult = $this->calculator->calculate($costCents, $maximumCents, $minimum);
            $maximumResult = $this->calculator->calculate($costCents, $maximumCents, $maximum);
        } catch (InvalidArgumentException $exception) {
            return $this->invalid($this->reason($exception->getMessage()), $costCents, $maximumCents);
        }

        $sellingRounding = $this->structuredRounding($rules['selling']['rounding'] ?? ($rules['rounding'] ?? ['mode' => 'none', 'increment' => 1]));
        $minimumRounding = $this->structuredRounding($rules['minimum']['rounding'] ?? ['mode' => 'none', 'increment' => 1]);
        $maximumRounding = $this->structuredRounding($rules['maximum']['rounding'] ?? ['mode' => 'none', 'increment' => 1]);
        if ($sellingRounding === null || $minimumRounding === null || $maximumRounding === null) {
            return $this->invalid('invalid_formula_rounding_rule', $costCents, $maximumCents);
        }
        $sellingResult['price'] = $this->roundCents($sellingResult['price'], $sellingRounding['mode'], $sellingRounding['increment']);
        $minimumResult['price'] = $this->roundCents($minimumResult['price'], $minimumRounding['mode'], $minimumRounding['increment']);
        $maximumResult['price'] = $this->roundCents($maximumResult['price'], $maximumRounding['mode'], $maximumRounding['increment']);

        $minimumCap = (bool) ($rules['minimum']['cap_enabled'] ?? false);
        $maximumCap = (bool) ($rules['maximum']['cap_enabled'] ?? false);
        $minCents = $minimumResult['price'];
        $maxCents = $maximumResult['price'];
        if ($minimumCap && $maximumCap && $minCents > $maxCents) {
            return $this->structuredBreakdown(
                PriceBreakdown::STATUS_CONFLICT,
                $costCents,
                $maximumCents,
                $selling,
                $sellingResult,
                $minimum,
                $minimumResult,
                $minimumCap,
                $maximum,
                $maximumResult,
                $maximumCap,
                null,
                null,
                'calculated_minimum_exceeds_maximum',
            );
        }

        $afterMinimum = $sellingResult['price'];
        if ($minimumCap) $afterMinimum = max($afterMinimum, $minCents);
        $afterMaximum = $afterMinimum;
        if ($maximumCap) $afterMaximum = min($afterMaximum, $maxCents);

        $regularSource = (string) ($rules['regular_price_source'] ?? 'capped_selling');
        $regular = $this->regularPrice(
            $regularSource,
            $costCents,
            $maximumCents,
            $sellingResult['price'],
            $minCents,
            $maxCents,
            $afterMaximum,
        );
        if ($regular === null) return $this->invalid('invalid_regular_price_source', $costCents, $maximumCents);
        $discount = $this->discount($regular, $rules['discount'] ?? []);
        if ($discount === null) return $this->invalid('invalid_discount_rule', $costCents, $maximumCents);
        $discountRounding = $this->structuredRounding($rules['discount_rounding'] ?? ($rules['discount']['rounding'] ?? ['mode' => 'none', 'increment' => 1]));
        if ($discountRounding === null) return $this->invalid('invalid_discount_rounding_rule', $costCents, $maximumCents);
        $priceAfterDiscount = $this->roundCents($discount['sale'], $discountRounding['mode'], $discountRounding['increment']);
        $sale = $priceAfterDiscount;
        $warnings = [];
        if (($rules['discount']['protect_minimum'] ?? true) && $minimumCap && $sale < $minCents) {
            $sale = $minCents;
        } elseif ($sale < $minCents) {
            $warnings[] = 'sale_below_minimum_cap_disabled';
        }
        if (! $maximumCap && $regular > $maxCents) $warnings[] = 'regular_price_above_maximum_cap_disabled';

        return $this->structuredBreakdown(
            PriceBreakdown::STATUS_VALID,
            $costCents,
            $maximumCents,
            $selling,
            $sellingResult,
            $minimum,
            $minimumResult,
            $minimumCap,
            $maximum,
            $maximumResult,
            $maximumCap,
            $afterMinimum,
            $afterMaximum,
            null,
            $regular,
            $regular - $sale,
            $sale,
            $warnings,
            $regular,
            $regular - $priceAfterDiscount,
            $priceAfterDiscount,
            $regularSource,
        );
    }

    private function isStructured(array $rules): bool
    {
        return array_key_exists('selling', $rules) || array_key_exists('minimum', $rules) || array_key_exists('maximum', $rules);
    }

    /** @param array<string,mixed> $rules */
    private function calculateLegacy(mixed $cost, mixed $supplierCeiling, array $rules): PriceBreakdown
    {
        $costCents = $this->toCents($cost);
        if ($costCents === null || $costCents <= 0) return $this->invalid('invalid_cost', $costCents);
        $minimum = $this->legacyMarkup($rules, 'minimum_markup', $costCents);
        $selling = $this->legacyMarkup($rules, 'selling_markup', $costCents);
        if ($minimum === null || $selling === null) return $this->invalid('invalid_markup_rule', $costCents);
        $supplierLimit = $this->toCents($supplierCeiling);
        $merchantLimit = array_key_exists('ceiling', $rules) && $rules['ceiling'] !== null ? $this->toCents($rules['ceiling']) : null;
        if (($supplierCeiling !== null && $supplierLimit === null) || (array_key_exists('ceiling', $rules) && $rules['ceiling'] !== null && $merchantLimit === null)) return $this->invalid('invalid_ceiling', $costCents);
        $minimumPrice = $costCents + $minimum;
        $target = max($minimumPrice, $costCents + $selling);
        $ceiling = $this->lowest($supplierLimit, $merchantLimit);
        if ($ceiling !== null && $minimumPrice > $ceiling) return $this->invalid('minimum_exceeds_ceiling', $costCents, $supplierLimit, $minimumPrice, $target, $ceiling, PriceBreakdown::STATUS_CONFLICT);
        $rounding = $this->legacyRounding($rules);
        if ($rounding === null) return $this->invalid('invalid_rounding_rule', $costCents);
        $warnings = [];
        $final = $this->roundCents($target, $rounding['mode'], $rounding['increment']);
        if ($final < $minimumPrice) {
            $final = $this->roundCents($minimumPrice, 'up', $rounding['increment']);
            $warnings[] = 'rounding_adjusted_to_preserve_minimum';
        }
        if ($ceiling !== null && $final > $ceiling) {
            $final = $this->roundCents($ceiling, 'down', $rounding['increment']);
            $warnings[] = 'selling_markup_limited_by_ceiling';
        }
        if ($final < $minimumPrice) return $this->invalid('rounding_or_ceiling_violates_minimum', $costCents, $supplierLimit, $minimumPrice, $target, $ceiling, PriceBreakdown::STATUS_CONFLICT);
        return new PriceBreakdown(PriceBreakdown::STATUS_VALID, $this->fromCents($costCents), $this->fromCents($minimumPrice), $this->fromCents($target), $this->fromCents($final), $this->fromCents($ceiling), warnings: $warnings, supplierMaximumPrice: $this->fromCents($supplierLimit));
    }

    private function legacyMarkup(array $rules, string $key, int $base): ?int
    {
        if (! array_key_exists($key, $rules) || $rules[$key] === null) return 0;
        $rule = $rules[$key];
        if (! is_array($rule) || ! isset($rule['type']) || ! array_key_exists('value', $rule)) return null;
        $value = $this->toCents($rule['value']);
        if ($value === null || $value < 0) return null;
        return match ($rule['type']) {
            'fixed' => $value,
            'percent' => intdiv(($base * $value) + 5000, 10000),
            default => null,
        };
    }

    private function legacyRounding(array $rules): ?array
    {
        $rule = $rules['rounding'] ?? ['mode' => 'none', 'increment' => 0.01];
        if (! is_array($rule)) return null;
        $increment = $this->toCents($rule['increment'] ?? 0.01);
        $mode = $rule['mode'] ?? 'none';
        return in_array($mode, ['none', 'up', 'down', 'nearest'], true) && $increment !== null && $increment > 0 ? ['mode' => $mode, 'increment' => $increment] : null;
    }

    private function structuredRounding(mixed $rule): ?array
    {
        if (! is_array($rule)) $rule = [];
        $mode = (string) ($rule['mode'] ?? 'none');
        $increment = $this->toCents($rule['increment'] ?? 0.01);
        return in_array($mode, ['none', 'up', 'down', 'nearest'], true) && $increment !== null && $increment > 0 ? ['mode' => $mode, 'increment' => $increment] : null;
    }

    private function discount(int $regular, mixed $rule): ?array
    {
        if (! is_array($rule)) $rule = [];
        $type = (string) ($rule['type'] ?? 'fixed');
        $value = $this->toCents($rule['value'] ?? 0);
        if ($value === null || $value < 0 || ! in_array($type, ['fixed', 'percent'], true)) return null;
        $amount = $type === 'percent' ? intdiv(($regular * $value) + 5000, 10000) : $value;
        return ['amount' => $amount, 'sale' => max(0, $regular - $amount)];
    }

    private function regularPrice(string $source, ?int $cost, ?int $maximum, int $selling, int $minimum, int $calculatedMaximum, int $cappedSelling): ?int
    {
        return match ($source) {
            'capped_selling' => $cappedSelling,
            'raw_selling' => $selling,
            'minimum' => $minimum,
            'maximum' => $calculatedMaximum,
            'supplier_cost' => $cost,
            'supplier_maximum' => $maximum,
            default => null,
        };
    }

    private function structuredBreakdown(string $status, ?int $cost, ?int $maximum, PriceRule $selling, array $sellingResult, PriceRule $minimum, array $minimumResult, bool $minimumCap, PriceRule $maximumRule, array $maximumResult, bool $maximumCap, ?int $afterMinimum, ?int $afterMaximum, ?string $reason = null, ?int $regular = null, ?int $discount = null, ?int $sale = null, array $warnings = [], ?int $discountBase = null, ?int $requestedDiscount = null, ?int $priceAfterDiscount = null, ?string $regularSource = null): PriceBreakdown
    {
        return new PriceBreakdown($status, $this->fromCents($cost), $this->fromCents($minimumResult['price']), $this->fromCents($sellingResult['price']), $this->fromCents($regular), $this->fromCents($maximumResult['price']), $reason, $warnings, $this->fromCents($maximum), $selling->base, $selling->adjustmentType, $selling->adjustmentValue, $this->fromCents($sellingResult['price']), $minimum->base, $minimum->adjustmentType, $minimum->adjustmentValue, $this->fromCents($minimumResult['price']), $minimumCap, $maximumRule->base, $maximumRule->adjustmentType, $maximumRule->adjustmentValue, $this->fromCents($maximumResult['price']), $maximumCap, $this->fromCents($afterMinimum), $this->fromCents($afterMaximum), $this->fromCents($regular), $this->fromCents($discount), $this->fromCents($sale), $cost === null || $regular === null ? null : $this->fromCents($regular - $cost), $cost === null || $sale === null ? null : $this->fromCents($sale - $cost), $status === PriceBreakdown::STATUS_CONFLICT, $reason, $this->fromCents($discountBase), $this->fromCents($requestedDiscount), $this->fromCents($priceAfterDiscount), $regularSource);
    }

    private function invalid(string $reason, ?int $cost = null, ?int $maximum = null, ?int $minimum = null, ?int $target = null, ?int $ceiling = null, string $status = PriceBreakdown::STATUS_INVALID): PriceBreakdown
    {
        return new PriceBreakdown($status, $this->fromCents($cost), $this->fromCents($minimum), $this->fromCents($target), null, $this->fromCents($ceiling), $reason, supplierMaximumPrice: $this->fromCents($maximum), pricingConflict: $status === PriceBreakdown::STATUS_CONFLICT, conflictReason: $reason);
    }

    private function reason(string $message): string
    {
        return str_contains($message, 'Maximum') ? 'missing_supplier_maximum' : (str_contains($message, 'Cost') ? 'missing_supplier_cost' : 'invalid_price');
    }

    private function roundCents(int $price, string $mode, int $increment): int
    {
        if ($mode === 'none') return $price;
        $quotient = intdiv($price, $increment);
        $remainder = $price % $increment;
        return match ($mode) {
            'up' => $remainder === 0 ? $price : ($quotient + 1) * $increment,
            'down' => $quotient * $increment,
            'nearest' => ($remainder * 2 >= $increment ? $quotient + 1 : $quotient) * $increment,
            default => $price,
        };
    }

    private function lowest(?int ...$values): ?int
    {
        $values = array_values(array_filter($values, static fn (?int $v): bool => $v !== null));
        return $values === [] ? null : min($values);
    }

    private function toCents(mixed $value): ?int
    {
        if (is_int($value)) return $value * 100;
        if (is_float($value)) return is_finite($value) ? (int) round($value * 100) : null;
        if (! is_string($value) || ! preg_match('/^\s*([0-9]+)(?:\.([0-9]{1,2}))?\s*$/', $value, $m)) return null;
        return ((int) $m[1] * 100) + (int) str_pad($m[2] ?? '0', 2, '0');
    }

    private function fromCents(?int $value): ?float
    {
        return $value === null ? null : $value / 100;
    }
}
