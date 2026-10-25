<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('inventory.view') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string'],
            'category' => ['nullable', 'integer'],
            'supplier' => ['nullable', 'integer'],
            'stock_level' => ['nullable', 'string'],
            'stock' => ['nullable', 'string'],
            'visibility' => ['nullable', 'string'],
            'source' => ['nullable', 'string'],
            'product_source' => ['nullable', 'string'],
            'type' => ['nullable', 'string'],
            'order' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer'],
            'sourcing' => ['nullable', 'string', 'in:enabled,disabled'],
        ];
    }
}
