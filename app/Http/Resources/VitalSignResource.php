<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VitalSignResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'encounter_id' => $this->encounter_id,
            'recorded_by' => $this->recorded_by,
            'temperature_c' => $this->temperature,
            'blood_pressure_systolic' => $this->blood_pressure_systolic,
            'blood_pressure_diastolic' => $this->blood_pressure_diastolic,
            'pulse_rate' => $this->pulse_rate,
            'respiratory_rate' => $this->respiratory_rate,
            'spo2' => $this->oxygen_saturation,
            'weight_kg' => $this->weight_kg,
            'height_cm' => $this->height_cm,
            'blood_glucose' => $this->blood_glucose,
            'pain_score' => $this->pain_score,
            'is_abnormal' => (bool) $this->is_abnormal,
            'early_warning_score' => $this->early_warning_score,
            'recorded_at' => $this->recorded_at,
        ];
    }
}
