<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('products.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'product_type' => ['nullable', 'string', 'in:simple,variable'],
            'category_id' => ['required', 'exists:categories,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:dropship_suppliers,id'],
            'source_type' => ['nullable', 'string', 'in:manual,dropshipping,other'],
            'purchase_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'name' => ['required', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:180'],
            'sku' => ['nullable', 'string', 'max:60', Rule::unique('products', 'sku')->ignore($this->route('product')?->id)],
            'brand' => ['nullable', 'string', 'max:80'],
            'short_description' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'regular_price' => ['nullable', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'selling_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'minimum_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'maximum_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'pricing_mode' => ['sometimes', 'required', 'in:auto,manual'],
            'enforce_minimum_price' => ['sometimes', 'boolean'],
            'enforce_maximum_price' => ['sometimes', 'boolean'],
            'protect_minimum_price' => ['sometimes', 'boolean'],
            'sourced_stock_quantity' => ['prohibited'],
            'allow_backorder' => ['sometimes', 'required', 'boolean'],
            'stock_quantity' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'unit' => ['nullable', 'string', 'max:40'],
            'meta_title' => ['nullable', 'string', 'max:180'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'meta_keywords' => ['nullable', 'string', 'max:500'],
            'size_variants' => ['nullable', 'array'],
            'size_variants.*.value' => ['nullable', 'string', 'max:60'],
            'size_variants.*.price_delta' => ['nullable', 'numeric'],
            'size_variants.*.stock' => ['nullable', 'integer', 'min:0'],
            'size_variants.*.image_path' => ['nullable', 'string', 'max:500'],
            'size_variants.*.image_b64' => ['nullable', 'string'],
            'size_variants.*.image_name' => ['nullable', 'string', 'max:255'],
            'color_variants' => ['nullable', 'array'],
            'color_variants.*.value' => ['nullable', 'string', 'max:60'],
            'color_variants.*.color_code' => ['nullable', 'string', 'max:30'],
            'color_variants.*.price_delta' => ['nullable', 'numeric'],
            'color_variants.*.stock' => ['nullable', 'integer', 'min:0'],
            'color_variants.*.image_path' => ['nullable', 'string', 'max:500'],
            'color_variants.*.image_b64' => ['nullable', 'string'],
            'color_variants.*.image_name' => ['nullable', 'string', 'max:255'],
            'weight_variants' => ['nullable', 'array'],
            'weight_variants.*.value' => ['nullable', 'string', 'max:60'],
            'weight_variants.*.price_delta' => ['nullable', 'numeric'],
            'weight_variants.*.stock' => ['nullable', 'integer', 'min:0'],
            'weight_variants.*.image_path' => ['nullable', 'string', 'max:500'],
            'weight_variants.*.image_b64' => ['nullable', 'string'],
            'weight_variants.*.image_name' => ['nullable', 'string', 'max:255'],
            'custom_variant_groups' => ['nullable', 'array'],
            'custom_variant_groups.*.name' => ['nullable', 'string', 'max:60'],
            'custom_variant_groups.*.options' => ['nullable', 'array'],
            'custom_variant_groups.*.options.*.value' => ['nullable', 'string', 'max:60'],
            'custom_variant_groups.*.options.*.price_delta' => ['nullable', 'numeric'],
            'custom_variant_groups.*.options.*.stock' => ['nullable', 'integer', 'min:0'],
            'custom_variant_groups.*.options.*.image_path' => ['nullable', 'string', 'max:500'],
            'custom_variant_groups.*.options.*.image_b64' => ['nullable', 'string'],
            'custom_variant_groups.*.options.*.image_name' => ['nullable', 'string', 'max:255'],
            'spec_labels' => ['nullable', 'array'],
            'spec_labels.*' => ['nullable', 'string', 'max:120'],
            'spec_values' => ['nullable', 'array'],
            'spec_values.*' => ['nullable', 'string', 'max:255'],
        ];
    }
}
