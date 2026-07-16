<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LabResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lab_order_id' => $this->lab_order_id,
            'result_value' => $this->result_value,
            'unit' => $this->unit,
            'reference_range' => $this->reference_range,
            'interpretation' => $this->interpretation,
            'is_critical' => (bool) $this->is_critical,
            'is_abnormal' => (bool) $this->is_abnormal,
            'entered_by' => $this->entered_by,
            'verified_by' => $this->verified_by,
            'result_date' => $this->result_date,
        ];
    }
}
