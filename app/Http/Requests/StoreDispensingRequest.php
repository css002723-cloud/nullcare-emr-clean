<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDispensingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quantity_dispensed' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
            // pharmacist marks whether this dispense completes the
            // prescription (status -> dispensed) or is a partial fill
            // (status -> partially_dispensed), e.g. when stock is short.
            'final' => ['required', 'boolean'],
            // pharmacist must explicitly acknowledge a shown allergy conflict
            // before we let a dispense through anyway (e.g. clinician already
            // cleared it) — omit this field and a conflict blocks the request.
            'override_allergy_warning' => ['sometimes', 'boolean'],
        ];
    }
}
