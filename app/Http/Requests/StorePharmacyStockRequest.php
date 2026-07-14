<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePharmacyStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'drug_name' => ['required', 'string', 'max:150'],
            'batch_number' => ['nullable', 'string', 'max:50'],
            'quantity_available' => ['required', 'integer', 'min:0'],
            'reorder_threshold' => ['required', 'integer', 'min:0'],
            'expiry_date' => ['nullable', 'date', 'after:today'],
            'is_controlled' => ['sometimes', 'boolean'],
        ];
    }
}
