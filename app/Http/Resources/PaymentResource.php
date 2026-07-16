<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'amount' => (float) $this->amount_paid,
            'payment_method' => $this->payment_method,
            'payment_reference' => $this->payment_reference,
            'received_by' => $this->received_by,
            'paid_at' => $this->paid_at,
        ];
    }
}
