<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClinicalNoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'encounter_id' => $this->encounter_id,
            'note_type' => $this->note_type,
            'diagnosis' => $this->diagnosis,
            'plan' => $this->plan,
            'body' => $this->body,
            'recorded_by' => $this->recorded_by,
            'created_at' => $this->created_at,
        ];
    }
}
