<?php

namespace App\Services;

use App\Models\Product;
use Generator;
use InvalidArgumentException;

/** One option per variant type, using the same additive pricing as the cart. */
class ProductOfferService
{
    public function groups(Product $product): array
    {
        return $product->variants->groupBy('type')->map(fn ($options) => $options->values()->all())->all();
    }

    public function selected(Product $product, ?string $ids = null): array
    {
        $groups = $this->groups($product);
        $selected = [];
        $requested = $ids === null ? null : explode('.', $ids);
        if ($requested !== null && (count($requested) !== count($groups) || preg_match('/^\d+(?:\.\d+)*$/D', $ids) !== 1)) {
            throw new InvalidArgumentException('Unknown product variant.');
        }
        foreach ($groups as $type => $options) {
            $matches = $requested === null ? [$options[0]] : array_values(array_filter($options, fn ($option) => in_array((string) $option->id, $requested, true)));
            if (count($matches) !== 1) {
                throw new InvalidArgumentException('Unknown product variant.');
            }
            $selected[$type] = $matches[0];
        }

        return $this->offering($product, $selected);
    }

    public function all(Product $product): Generator
    {
        yield from $this->combinations($product, array_values($this->groups($product)), []);
    }

    private function combinations(Product $product, array $groups, array $selected): Generator
    {
        if ($groups === []) {
            yield $this->offering($product, $selected);

            return;
        }
        $options = array_shift($groups);
        foreach ($options as $option) {
            yield from $this->combinations($product, $groups, [...$selected, $option->type => $option]);
        }
    }

    private function offering(Product $product, array $selected): array
    {
        $values = array_map(fn ($option) => $option->value, $selected);
        $ids = implode('.', array_map(fn ($option) => $option->id, $selected));
        $label = implode(', ', array_map(fn ($type, $value) => $type.': '.$value, array_keys($values), $values));
        $url = route('product.show', $product).($ids !== '' ? '?variant='.$ids : '');

        return [
            'id' => (string) $product->id.($ids !== '' ? '-'.$ids : ''),
            'sku' => (string) ($product->sku ?: $product->id).($ids !== '' ? '-'.$ids : ''),
            'url' => $url, 'selection' => $values, 'label' => $label,
            'price' => $product->unitPriceForVariant($label ?: null),
            // Checkout consumes parent stock. Variant option stock is not a
            // substitute for an orderable parent product.
            'in_stock' => $product->availableStockQuantity() > 0,
            'availability' => $product->availableStockQuantity() > 0 ? 'in_stock' : ($product->allow_backorder ? 'backorder' : 'out_of_stock'),
        ];
    }
}
