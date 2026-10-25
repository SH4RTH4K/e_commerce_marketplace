<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReceiveOrderStockRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return ($this->user()?->hasPermission('orders.execute') ?? false)
            && ($this->user()?->hasPermission('inventory.edit') ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'expected_outstanding' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'reference' => ['nullable', 'string', 'max:120'],
        ];
    }
}
