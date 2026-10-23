<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission($this->input('bulk_action') === 'delete' ? 'products.delete' : 'products.create') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'bulk_action' => ['required', 'string', 'in:activate,deactivate,delete,move_category,enable_sourcing,disable_sourcing'],
            'category_id' => ['required_if:bulk_action,move_category', 'nullable', 'integer', 'exists:categories,id'],
        ];
    }
}
