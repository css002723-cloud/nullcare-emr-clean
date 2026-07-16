<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LabOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'encounter_id' => $this->encounter_id,
            'patient_id' => $this->patient_id,
            'ordered_by' => $this->ordered_by,
            'test_code' => $this->test_code,
            'test_name' => $this->test_name,
            'loinc_code' => $this->loinc_code ?? $this->whenLoaded('catalogEntry', fn () => $this->catalogEntry?->loinc_code),
            'loinc_display' => $this->whenLoaded('catalogEntry', fn () => $this->catalogEntry?->loinc_display, $this->test_name),
            'specimen_type' => $this->specimen_type,
            'barcode' => $this->barcode,
            'status' => $this->status === 'completed' ? 'resulted' : $this->status,
            'priority' => $this->urgency,
            'result' => new LabResultResource($this->whenLoaded('result')),
            'ordered_at' => $this->ordered_at,
        ];
    }
}
