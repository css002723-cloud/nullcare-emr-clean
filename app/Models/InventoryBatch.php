<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryBatch extends Model
{
    protected $fillable = [
        'client_uuid', 'item_id', 'batch_number', 'quantity_received', 'quantity_on_hand',
        'expiry_date', 'received_date', 'supplier', 'unit_cost', 'received_by', 'synced',
    ];

    protected function casts(): array
    {
        return ['expiry_date' => 'date', 'received_date' => 'date', 'synced' => 'boolean'];
    }

    public function item()
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
