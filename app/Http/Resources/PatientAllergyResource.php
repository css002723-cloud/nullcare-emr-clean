<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientAllergyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'substance' => $this->allergen,
            'reaction' => $this->reaction,
            'severity' => $this->severity,
            'recorded_by' => $this->recorded_by,
            'created_at' => $this->created_at,
        ];
    }
}
