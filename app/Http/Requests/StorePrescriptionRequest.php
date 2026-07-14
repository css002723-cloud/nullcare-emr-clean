<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePrescriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'drug_name' => ['required', 'string', 'max:150'],
            'formulation' => ['nullable', 'string', 'max:100'],
            'dose' => ['required', 'string', 'max:50'],
            'route' => ['nullable', 'string', 'max:50'],
            'frequency' => ['nullable', 'string', 'max:50'],
            'duration' => ['nullable', 'string', 'max:50'],
        ];
    }
}
