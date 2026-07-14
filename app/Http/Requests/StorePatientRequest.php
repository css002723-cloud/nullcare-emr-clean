<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route-level 'role' middleware handles access control
    }

    public function rules(): array
    {
        return [
            'national_id' => ['nullable', 'string', 'max:30'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['required', 'in:male,female,other'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'age_estimate' => ['nullable', 'integer', 'min:0', 'max:150'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'village' => ['nullable', 'string', 'max:100'],
            'traditional_authority' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'occupation' => ['nullable', 'string', 'max:100'],
            'patient_category' => ['required', 'in:outpatient,inpatient,student,staff,private,emergency,research'],
            'guardian_name' => ['nullable', 'string', 'max:150'],
            'guardian_phone' => ['nullable', 'string', 'max:20'],
            'guardian_relationship' => ['nullable', 'string', 'max:50'],
            'consent_care' => ['boolean'],
            'consent_teaching' => ['boolean'],
            'consent_research' => ['boolean'],
            // set true once the receptionist has reviewed possible-duplicate
            // candidates returned by GET /patients/check-duplicates and
            // confirmed this is a genuinely new person
            'confirm_new_patient' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'gender.in' => 'Gender must be male, female, or other.',
            'patient_category.in' => 'Please select a valid patient category.',
        ];
    }
}
