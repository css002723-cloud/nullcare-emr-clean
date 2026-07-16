<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReferralResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'encounter_id' => $this->encounter_id,
            'to_department' => $this->to_department,
            'reason' => $this->reason,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
        ];
    }
}
