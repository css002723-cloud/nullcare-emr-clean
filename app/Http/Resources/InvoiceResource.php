<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'patient' => new PatientResource($this->whenLoaded('patient')),
            'encounter_id' => $this->encounter_id,
            'payer_type' => $this->payer_type,
            'total_amount' => (float) $this->total_amount,
            'amount_paid' => $this->whenLoaded('payments', fn () => (float) $this->payments->sum('amount_paid')),
            'status' => $this->status,
            'created_by' => $this->created_by,
            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'created_at' => $this->created_at,
        ];
    }
}
