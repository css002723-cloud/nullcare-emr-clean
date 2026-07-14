<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'encounter_id' => ['nullable', 'integer', 'exists:encounters,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.service_name' => ['required', 'string', 'max:150'],
            'items.*.amount' => ['required', 'numeric', 'min:0'],
        ];
    }
}
