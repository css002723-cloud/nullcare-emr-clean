<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVitalSignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'temperature' => ['nullable', 'numeric', 'between:30,45'],
            'blood_pressure_systolic' => ['nullable', 'integer', 'between:50,260'],
            'blood_pressure_diastolic' => ['nullable', 'integer', 'between:30,180'],
            'pulse_rate' => ['nullable', 'integer', 'between:20,250'],
            'respiratory_rate' => ['nullable', 'integer', 'between:5,60'],
            'oxygen_saturation' => ['nullable', 'integer', 'between:50,100'],
            'weight_kg' => ['nullable', 'numeric', 'between:0,400'],
            'height_cm' => ['nullable', 'numeric', 'between:0,250'],
            'blood_glucose' => ['nullable', 'numeric', 'between:0,40'],
            'pain_score' => ['nullable', 'integer', 'between:0,10'],
        ];
    }
}
