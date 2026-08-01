<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryConsumption extends Model
{
    const UPDATED_AT = null;

    // Explicit table name: Eloquent's default convention would pluralize
    // to `inventory_consumptions`, but the migration created the table as
    // `inventory_consumption` (singular, matching the Python reference).
    protected $table = 'inventory_consumption';

    protected $fillable = [
        'client_uuid', 'item_id', 'batch_id', 'quantity', 'department', 'encounter_id',
        'patient_id', 'reason', 'consumed_by', 'synced',
    ];

    protected function casts(): array
    {
        return ['synced' => 'boolean'];
    }

    public function item()
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }

    public function batch()
    {
        return $this->belongsTo(InventoryBatch::class, 'batch_id');
    }

    public function encounter()
    {
        return $this->belongsTo(Encounter::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function consumedBy()
    {
        return $this->belongsTo(User::class, 'consumed_by');
    }
}
