<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EncounterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'encounter_number' => $this->encounter_number,
            'mrn' => $this->mrn,
            'patient_id' => $this->patient_id,
            'visit_type' => $this->visit_type,
            'stage' => $this->stage,
            'priority' => $this->priority,
            'is_emergency' => (bool) $this->is_emergency,
            'chief_complaint' => $this->chief_complaint,
            'current_department' => $this->current_department,
            'assigned_clinician_id' => $this->assigned_clinician_id,
            'ward' => $this->ward,
            'bed' => $this->bed,
            'admission_diagnosis' => $this->admission_diagnosis,
            'outcome' => $this->outcome,
            'disposition_notes' => $this->disposition_notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'client_uuid' => $this->client_uuid,
        ];
    }
}
