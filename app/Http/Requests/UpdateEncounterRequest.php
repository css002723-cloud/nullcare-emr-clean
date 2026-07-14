<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEncounterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Clinicians fill these in progressively over the course of a visit —
            // all optional here so partial saves (e.g. autosave drafts) work.
            'triage_category' => ['sometimes', 'nullable', 'in:emergency,urgent,routine'],
            'presenting_complaint' => ['sometimes', 'nullable', 'string'],
            'history' => ['sometimes', 'nullable', 'string'],
            'examination_findings' => ['sometimes', 'nullable', 'string'],
            'diagnosis' => ['sometimes', 'nullable', 'string', 'max:255'],
            'diagnosis_code' => ['sometimes', 'nullable', 'string', 'max:20'],
            'clinical_plan' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'in:open,closed,referred,admitted,discharged'],
        ];
    }
}
