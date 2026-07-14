<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLabOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'test_name' => ['required', 'string', 'max:150'],
            'loinc_code' => ['nullable', 'string', 'max:20'],
            'specimen_type' => ['nullable', 'string', 'max:100'],
            'urgency' => ['required', 'in:routine,urgent,stat'],
        ];
    }
}
