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
            'client_uuid' => $this->client_uuid,
            'patient_id' => $this->patient_id,
            'patient' => new PatientResource($this->whenLoaded('patient')),
            'clinician_id' => $this->clinician_id,
            'department_id' => $this->department_id,
            'encounter_type' => $this->encounter_type,
            'visit_type' => $this->encounter_type,
            'priority' => $this->triage_category,
            'chief_complaint' => $this->presenting_complaint,
            'history' => $this->history,
            'examination_findings' => $this->examination_findings,
            'diagnosis' => $this->diagnosis,
            'diagnosis_code' => $this->diagnosis_code,
            'clinical_plan' => $this->clinical_plan,
            'disposition_notes' => $this->disposition_notes,
            'status' => $this->status,
            'stage' => $this->stage,
            'current_department' => $this->current_department,
            'vital_signs' => VitalSignResource::collection($this->whenLoaded('vitalSigns')),
            'lab_orders' => LabOrderResource::collection($this->whenLoaded('labOrders')),
            'prescriptions' => PrescriptionResource::collection($this->whenLoaded('prescriptions')),
            'notes' => ClinicalNoteResource::collection($this->whenLoaded('clinicalNotes')),
            'orders' => ClinicalOrderResource::collection($this->whenLoaded('clinicalOrders')),
            'referrals' => ReferralResource::collection($this->whenLoaded('referrals')),
            'safety_alerts' => $this->when(
                $this->relationLoaded('patient') && $this->patient?->relationLoaded('allergies'),
                fn () => ['allergies' => PatientAllergyResource::collection($this->patient->allergies)]
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
