<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DischargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'discharge_summary' => ['required', 'string'],
            'outcome' => ['required', 'in:discharged,transferred,died'],
        ];
    }
}
