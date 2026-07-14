<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAdmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ward_name' => ['required', 'string', 'max:100'],
            'bed_number' => ['nullable', 'string', 'max:20'],
            'admission_diagnosis' => ['nullable', 'string', 'max:255'],
        ];
    }
}
