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
            'patient_uid' => $this->patient_uid,
            'national_id' => $this->national_id,
            'given_name' => $this->given_name,
            'family_name' => $this->family_name,
            'full_name' => "{$this->given_name} {$this->family_name}",
            'sex' => $this->sex,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'estimated_age' => $this->estimated_age,
            'phone' => $this->phone,
            'village' => $this->village,
            'traditional_authority' => $this->traditional_authority,
            'district' => $this->district,
            'region' => $this->region,
            'occupation' => $this->occupation,
            'guardian_name' => $this->guardian_name,
            'guardian_relationship' => $this->guardian_relationship,
            'guardian_phone' => $this->guardian_phone,
            'patient_category' => $this->patient_category,
            'consent_care' => (bool) $this->consent_care,
            'consent_research' => (bool) $this->consent_research,
            'consent_teaching' => (bool) $this->consent_teaching,
            'is_deceased' => (bool) $this->is_deceased,
            'merged_into_patient_id' => $this->merged_into_patient_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'client_uuid' => $this->client_uuid,
        ];
    }
}
