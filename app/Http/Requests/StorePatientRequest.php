<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'national_id' => 'nullable|string|unique:patients,national_id',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'gender' => 'required|in:male,female,other',
            'date_of_birth' => 'nullable|date|before:today',
            'age_estimate' => 'nullable|integer|min:0|max:150',
            'phone' => 'nullable|string|unique:patients,phone',
            'email' => 'nullable|email|unique:patients,email',
            'address' => 'nullable|string|max:255',
            'village' => 'nullable|string|max:100',
            'traditional_authority' => 'nullable|string|max:100',
            'district' => 'nullable|string|max:100',
            'occupation' => 'nullable|string|max:100',
            'patient_category' => 'nullable|string|max:100',
            'guardian_name' => 'nullable|string|max:100',
            'guardian_phone' => 'nullable|string',
            'guardian_relationship' => 'nullable|string|max:100',
            'consent_care' => 'boolean',
            'consent_teaching' => 'boolean',
            'consent_research' => 'boolean',
            'confirm_new_patient' => 'required|accepted',
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'First name is required',
            'last_name.required' => 'Last name is required',
            'gender.required' => 'Gender is required',
            'national_id.unique' => 'This national ID is already registered',
            'phone.unique' => 'This phone number is already in use',
            'email.unique' => 'This email is already in use',
            'confirm_new_patient.required' => 'Please confirm this is a new patient',
        ];
    }
}
