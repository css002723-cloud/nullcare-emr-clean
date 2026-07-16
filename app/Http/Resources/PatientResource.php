<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_uuid' => $this->client_uuid,
            'mrn' => $this->patient_number,
            'given_name' => $this->first_name,
            'family_name' => $this->last_name,
            'full_name' => $this->full_name,
            'sex' => $this->gender,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'estimated_age' => $this->age_estimate,
            'national_id' => $this->national_id,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'village' => $this->village,
            'traditional_authority' => $this->traditional_authority,
            'district' => $this->district,
            'region' => $this->region,
            'occupation' => $this->occupation,
            'patient_category' => $this->patient_category,
            'guardian_name' => $this->guardian_name,
            'guardian_phone' => $this->guardian_phone,
            'guardian_relationship' => $this->guardian_relationship,
            'consent_care' => (bool) $this->consent_care,
            'consent_teaching' => (bool) $this->consent_teaching,
            'consent_research' => (bool) $this->consent_research,
            'is_deceased' => (bool) $this->is_deceased,
            'referred_doctor' => $this->referred_doctor,
            'referred_doctor_department' => $this->referred_doctor_department,
            'completion_status' => $this->completion_status,
            'is_duplicate_of' => $this->is_duplicate_of,
            'allergies' => PatientAllergyResource::collection($this->whenLoaded('allergies')),
            'encounters' => EncounterResource::collection($this->whenLoaded('encounters')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
