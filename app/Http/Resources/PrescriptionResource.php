<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PrescriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'encounter_id' => $this->encounter_id,
            'patient_id' => $this->patient_id,
            'prescribed_by' => $this->prescribed_by,
            'drug_name' => $this->drug_name,
            'formulation' => $this->formulation,
            'dose' => $this->dose,
            'route' => $this->route,
            'frequency' => $this->frequency,
            'duration' => $this->duration,
            'status' => $this->status,
            'is_pediatric_dose' => (bool) $this->is_pediatric_dose,
            // Raw JSON string on purpose — Pharmacy.jsx does its own JSON.parse()
            // on this field when listing prescriptions.
            'cds_alerts' => $this->cds_alerts,
            'created_at' => $this->created_at,
        ];
    }
}
