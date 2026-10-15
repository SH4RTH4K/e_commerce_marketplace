<?php

namespace App\Services;

use App\Models\Product;
use App\Services\Dropshipping\PriceBreakdown;
use App\Services\Dropshipping\PricingEngine;
use Illuminate\Validation\ValidationException;

class ProductPricingService
{
    public function __construct(private readonly PricingEngine $engine) {}

    /** Resolve older imports without changing their saved customer prices. */
    public function inventoryDetails(Product $product): array
    {
        $details = $product->only(['selling_price', 'minimum_price', 'maximum_price']);
        $details['price_details_source'] = null;
        if (! in_array(null, array_values($product->only(['selling_price', 'minimum_price', 'maximum_price'])), true)) {
            return $details;
        }

        $product->loadMissing('supplierLinks.supplierProduct.supplier');
        $link = $product->supplierLinks->sortBy('id')->sortBy(fn ($link) => $link->sync_status === 'active' ? 0 : 1)->first();
        $source = $link?->supplierProduct;
        $suggested = null;
        $origin = null;
        if ($product->pricing_mode !== 'manual' && ! $link?->price_override && $source?->currency === 'BDT') {
            $snapshot = $link->pricing_snapshot ?? [];
            if (($snapshot['status'] ?? null) === PriceBreakdown::STATUS_VALID) {
                $suggested = [
                    'selling_price' => $snapshot['priceAfterMaximumCap'] ?? $snapshot['finalPrice'] ?? null,
                    'minimum_price' => $snapshot['calculatedMinimumPrice'] ?? $snapshot['minimumPrice'] ?? null,
                    'maximum_price' => $snapshot['calculatedMaximumPrice'] ?? $snapshot['ceilingPrice'] ?? null,
                ];
                $origin = 'supplier_snapshot';
            } elseif ($source->supplier) {
                $price = $this->engine->calculate($source->cost_price, $source->max_price, $source->supplier->pricing_rules ?? []);
                if ($price->isValid()) {
                    $suggested = self::fields($price, $source->supplier->pricing_rules ?? []);
                    $origin = 'supplier_rules';
                }
            }
        }
        foreach (['selling_price', 'minimum_price', 'maximum_price'] as $field) {
            if ($details[$field] === null && isset($suggested[$field])) {
                $details[$field] = number_format((float) $suggested[$field], 2, '.', '');
                $details['price_details_source'] = $origin;
            }
        }
        // Before separate selling prices existed, local/manual products used Regular.
        $details['selling_price'] ??= $product->regular_price;

        return $details;
    }

    public function context(Product $product): array
    {
        $link = $product->supplierLinks()->with('supplierProduct.supplier')
            ->orderByRaw("CASE WHEN sync_status = 'active' THEN 0 ELSE 1 END")->orderBy('id')->first();
        $source = $link?->supplierProduct;
        if (! $source || ! $source->supplier) {
            return ['linked' => false, 'mode' => 'manual', 'suggested' => null];
        }

        $price = $this->engine->calculate($source->cost_price, $source->max_price, $source->supplier->pricing_rules ?? []);
        return [
            'linked' => true,
            'mode' => $product->pricing_mode === 'manual' || $link->price_override ? 'manual' : 'auto',
            'supplier' => $source->supplier->name,
            'cost' => $source->cost_price,
            'supplier_maximum' => $source->max_price,
            'regular_source' => $price->regularPriceSource ?? 'capped_selling',
            'raw_selling' => $price->rawSellingPrice ?? $price->targetSellingPrice,
            'suggested' => $source->currency === 'BDT' && $price->isValid() ? self::fields($price, $source->supplier->pricing_rules ?? []) : null,
            'reason' => $source->currency !== 'BDT' ? 'Supplier currency must be BDT.' : $price->reason,
        ];
    }

    public static function fields(PriceBreakdown $price, array $rules = []): array
    {
        $regular = $price->regularSellingPrice ?? $price->finalPrice;
        $sale = $price->finalSalePrice;
        return [
            'selling_price' => $price->priceAfterMaximumCap ?? $price->finalPrice,
            'minimum_price' => $price->calculatedMinimumPrice ?? $price->minimumPrice,
            'maximum_price' => $price->calculatedMaximumPrice ?? $price->ceilingPrice,
            'regular_price' => $regular,
            'sale_price' => $sale !== null && $regular !== null && $sale < $regular ? $sale : null,
            'enforce_minimum_price' => $price->calculatedMinimumPrice !== null ? $price->minimumCapEnabled : $price->minimumPrice !== null,
            'enforce_maximum_price' => $price->calculatedMaximumPrice !== null ? $price->maximumCapEnabled : $price->ceilingPrice !== null,
            'protect_minimum_price' => (bool) ($rules['discount']['protect_minimum'] ?? true),
        ];
    }

    public function prepare(array $data, ?Product $product = null): array
    {
        if (! array_key_exists('pricing_mode', $data)) {
            // Older clients may still update just Regular/Sale. Preserve those
            // intentional edits during subsequent supplier syncs as well.
            if ($product && (($data['regular_price'] ?? $product->regular_price) != $product->regular_price
                || (array_key_exists('sale_price', $data) && $data['sale_price'] != $product->sale_price))) {
                $data['pricing_mode'] = 'manual';
            } else {
                return $data;
            }
        }

        if ($data['pricing_mode'] === 'auto') {
            $context = $product ? $this->context($product) : null;
            if (! ($context['suggested'] ?? null)) {
                throw ValidationException::withMessages(['pricing_mode' => 'Supplier pricing is unavailable or conflicting. Choose a product override to enter prices manually.']);
            }
            return array_merge($data, $context['suggested']);
        }

        foreach (['selling_price', 'minimum_price', 'maximum_price'] as $field) {
            $value = array_key_exists($field, $data) ? $data[$field] : $product?->$field;
            $data[$field] = $value !== null && $value !== '' ? round((float) $value, 2) : null;
        }
        $data['selling_price'] ??= (float) $data['regular_price'];
        foreach (['enforce_minimum_price', 'enforce_maximum_price', 'protect_minimum_price'] as $field) {
            $data[$field] = (bool) ($data[$field] ?? $product?->$field ?? ($field === 'protect_minimum_price'));
        }
        $minimum = $data['enforce_minimum_price'] ? $data['minimum_price'] : null;
        $maximum = $data['enforce_maximum_price'] ? $data['maximum_price'] : null;
        $customerPrice = $data['sale_price'] ?? $data['regular_price'];
        $errors = [];
        if ($data['enforce_minimum_price'] && $minimum === null) $errors['minimum_price'] = 'Enter a minimum price or disable its cap.';
        if ($data['enforce_maximum_price'] && $maximum === null) $errors['maximum_price'] = 'Enter a maximum price or disable its cap.';
        if ($minimum !== null && $maximum !== null && $minimum > $maximum) $errors['minimum_price'] = 'Minimum cannot exceed maximum when both caps are enabled.';
        if ($minimum !== null && $data['selling_price'] < $minimum) $errors['selling_price'] = 'Selling price is below the enabled minimum.';
        if ($maximum !== null && $data['selling_price'] > $maximum) $errors['selling_price'] = 'Selling price exceeds the enabled maximum.';
        if ($data['sale_price'] !== null && $data['sale_price'] > $data['regular_price']) $errors['sale_price'] = 'Discounted price cannot exceed regular price.';
        if ($minimum !== null && $data['protect_minimum_price'] && $customerPrice < $minimum) $errors['sale_price'] = 'Customer price is below the protected minimum.';
        if ($maximum !== null && $customerPrice > $maximum) $errors['sale_price'] = 'Customer price exceeds the enabled maximum.';
        if ($errors) throw ValidationException::withMessages($errors);

        return $data;
    }
}
