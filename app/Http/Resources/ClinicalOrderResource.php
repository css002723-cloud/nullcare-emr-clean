<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClinicalOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'encounter_id' => $this->encounter_id,
            'order_type' => $this->order_type,
            'details' => $this->details,
            'target_department' => $this->target_department,
            'priority' => $this->priority,
            'status' => $this->status,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
        ];
    }
}
