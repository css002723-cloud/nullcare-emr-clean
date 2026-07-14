<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEncounterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route-level 'role' middleware handles access control
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'appointment_id' => ['nullable', 'integer', 'exists:appointments,id'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'encounter_type' => ['required', 'in:outpatient,inpatient,emergency'],
            'triage_category' => ['nullable', 'in:emergency,urgent,routine'],
            'presenting_complaint' => ['nullable', 'string'],
        ];
    }
}
