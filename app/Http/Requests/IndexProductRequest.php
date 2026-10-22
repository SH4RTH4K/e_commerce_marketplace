<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('products.view') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:200'],
            'category' => ['nullable', 'integer', 'min:1'],
            'category_scope' => ['nullable', 'string', 'in:all,direct'],
            'exclude_category' => ['nullable', 'integer', 'min:1', 'exists:categories,id'],
            'supplier' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'string'],
            'stock' => ['nullable', 'string'],
            'stock_level' => ['nullable', 'string'],
            'type' => ['nullable', 'string'],
            'sort' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer'],
            'source' => ['nullable', 'string'],
        ];
    }
}
