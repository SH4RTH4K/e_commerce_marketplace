<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Str;

class ProductMetaDescriptionService
{
    /**
     * Build a compact, product-specific description for search and social metadata.
     */
    public function generate(Product $product): string
    {
        $name = $this->clip($this->clean($product->name) ?: 'Product', 64);
        $category = $this->clip($this->clean($product->category?->name) ?: 'online product', 28);
        $price = money($product->price);
        $reference = $this->clean($product->sku) ?: 'P'.$product->getKey();
        $reference = $this->clip($reference, 16);

        $openings = [
            "{$name} in {$category} at {$price}.",
            "Shop {$name}, a {$category}, for {$price}.",
            "{$category}: {$name}, priced {$price}.",
        ];
        $description = $openings[((int) $product->getKey()) % count($openings)];

        $ending = "Delivery across Bangladesh. Ref {$reference}.";
        foreach ($this->details($product) as $detail) {
            $remaining = 160 - mb_strlen($description) - mb_strlen($ending) - 2;
            if ($remaining < 12) {
                break;
            }

            $description = $this->append($description, $this->clip($detail, $remaining));
        }

        $description = trim($description.' '.$ending);

        return $this->clip($description, 160);
    }

    /**
     * @return list<string>
     */
    private function details(Product $product): array
    {
        $pairs = $this->specificationPairs($product);
        $details = [];

        foreach ($pairs as $label => $value) {
            $normalized = Str::lower($label);
            if (preg_match('/material|fabric|textile|composition|body/i', $normalized)) {
                $details[] = $value.' material';
                break;
            }
        }

        foreach ($pairs as $label => $value) {
            $normalized = Str::lower($label);
            if (preg_match('/feature|function|capacity|waterproof|style|pattern|design|use/i', $normalized)) {
                $details[] = $value;
                break;
            }
        }

        $variants = $this->variantDetails($product);
        foreach (['Colour' => 'colors', 'Size' => 'sizes'] as $label => $key) {
            if (($variants[$key] ?? []) !== []) {
                $details[] = implode(', ', $variants[$key]).' '.Str::lower($label);
            }
        }

        if ($details === []) {
            foreach ($pairs as $label => $value) {
                $details[] = $this->clean($label).' '.$value;
                if (count($details) >= 2) {
                    break;
                }
            }
        }

        if (count($details) < 2) {
            $descriptionDetail = $this->descriptionDetail($product);
            if ($descriptionDetail !== null) {
                $details[] = $descriptionDetail;
            }
        }

        return array_values(array_unique(array_filter($details)));
    }

    /**
     * @return array<string, string>
     */
    private function specificationPairs(Product $product): array
    {
        $specifications = is_array($product->specifications) ? $product->specifications : [];
        $pairs = [];

        foreach ($specifications as $key => $specification) {
            if (is_array($specification)) {
                $label = $specification['label'] ?? $specification['name'] ?? $specification['key'] ?? null;
                $value = $specification['value'] ?? $specification['text'] ?? null;
            } else {
                $label = $key;
                $value = $specification;
            }

            $label = $this->clean($label);
            $value = $this->clean($value);
            if ($label !== '' && $value !== '') {
                $pairs[$label] = $this->clip($value, 55);
            }
        }

        return $pairs;
    }

    /**
     * @return array{colors: list<string>, sizes: list<string>}
     */
    private function variantDetails(Product $product): array
    {
        $variants = $product->relationLoaded('variants')
            ? $product->variants
            : collect();

        $values = ['colors' => [], 'sizes' => []];
        foreach ($variants as $variant) {
            $type = Str::lower($this->clean($variant->type));
            $value = $this->clean($variant->value);
            if ($value === '') {
                continue;
            }

            if (str_contains($type, 'color') || str_contains($type, 'colour')) {
                $values['colors'][] = $value;
            } elseif (str_contains($type, 'size')) {
                $values['sizes'][] = $value;
            }
        }

        foreach ($values as $key => $items) {
            $values[$key] = array_slice(array_values(array_unique($items)), 0, 4);
        }

        return $values;
    }

    private function descriptionDetail(Product $product): ?string
    {
        $text = $this->clean($product->short_description ?: $product->description);
        if ($text === '' || preg_match('/view product details and availability|shop at .* in bangladesh/i', $text)) {
            return null;
        }

        $sentence = preg_split('/(?<=[.!?])\s+/u', $text, 2)[0] ?? $text;

        return $this->clip($sentence, 48);
    }

    private function append(string $base, string $addition): string
    {
        $candidate = trim($base.' '.trim($addition));
        if (mb_strlen($candidate) <= 160) {
            return $candidate;
        }

        $remaining = 160 - mb_strlen($base) - 1;
        if ($remaining < 12) {
            return $base;
        }

        $short = trim(mb_substr(trim($addition), 0, $remaining));
        $short = preg_replace('/\s+\S*$/u', '', $short) ?: $short;

        return trim($base.' '.$short, ' .;,').'.';
    }

    private function clean(mixed $value): string
    {
        $text = html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?: '';

        return trim($text, " \t\n\r\0\x0B.,;:-");
    }

    private function clip(string $value, int $limit): string
    {
        if (mb_strlen($value) <= $limit) {
            return trim($value);
        }

        $value = mb_substr($value, 0, $limit);
        $value = preg_replace('/\s+\S*$/u', '', $value) ?: $value;

        return trim($value, ' .;,-').'.';
    }
}
