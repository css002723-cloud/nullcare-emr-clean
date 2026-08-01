<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    const CATEGORIES = ['pharmacy', 'laboratory', 'imaging', 'theatre', 'ward'];

    protected $fillable = ['client_uuid', 'name', 'category', 'unit', 'reorder_level', 'is_controlled', 'department', 'notes', 'synced'];

    protected function casts(): array
    {
        return ['is_controlled' => 'boolean', 'synced' => 'boolean'];
    }

    public function batches()
    {
        return $this->hasMany(InventoryBatch::class, 'item_id');
    }

    public function consumptions()
    {
        return $this->hasMany(InventoryConsumption::class, 'item_id');
    }

    /**
     * Quantity on hand is derived from the sum of non-expired batches, per
     * the Python reference's design note — not a directly stored total.
     */
    public function quantityOnHand(): int
    {
        return $this->batches()
            ->where(function ($q) {
                $q->whereNull('expiry_date')->orWhere('expiry_date', '>=', now()->toDateString());
            })
            ->sum('quantity_on_hand');
    }

    public function isLowStock(): bool
    {
        return $this->quantityOnHand() <= $this->reorder_level;
    }
}
