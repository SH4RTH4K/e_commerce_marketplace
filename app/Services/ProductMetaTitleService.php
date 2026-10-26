<?php

namespace App\Services;

use App\Models\Product;

class ProductMetaTitleService
{
    public function generate(Product $product): string
    {
        $title = trim(strip_tags((string) ($product->meta_title ?: $product->name)));
        if ($title === '') {
            return '';
        }

        $name = mb_strtolower(strip_tags((string) $product->name));
        $skuDigits = preg_replace('/\D+/', '', (string) $product->sku) ?: '';
        $skuDigits = ltrim($skuDigits, '0') ?: '0';

        return preg_replace_callback(
            '/\s*\((\d{4,})\)(?=\s*(?:\||$))/u',
            static function (array $match) use ($name, $skuDigits): string {
                $reference = $match[1];
                $normalizedReference = ltrim($reference, '0') ?: '0';

                // Keep real model or SKU numbers; discard old catalog/import IDs
                // that were left at the end of SEO titles after product migration.
                if (str_contains($name, mb_strtolower($reference)) || $normalizedReference === $skuDigits) {
                    return $match[0];
                }

                return '';
            },
            $title,
        ) ?? $title;
    }
}
