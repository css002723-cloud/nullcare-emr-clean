<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PharmacyStockResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'drug_name' => $this->drug_name,
            'batch_number' => $this->batch_number,
            'quantity_on_hand' => $this->quantity_available,
            'unit' => $this->unit,
            'reorder_level' => $this->reorder_threshold,
            'low_stock' => $this->isLowStock(),
            'expiry_date' => $this->expiry_date?->toDateString(),
            'is_controlled' => (bool) $this->is_controlled,
            'updated_at' => $this->updated_at,
        ];
    }
}
