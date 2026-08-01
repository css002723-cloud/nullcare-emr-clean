<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DrugStock extends Model
{
    // Explicit table name required: migration creates `drug_stock`
    // (singular, matching the Python reference), but Eloquent's default
    // convention would guess `drug_stocks` — same bug we hit with
    // pharmacy_stock earlier in this project.
    protected $table = 'drug_stock';

    protected $fillable = ['client_uuid', 'drug_name', 'quantity_on_hand', 'reorder_level', 'unit', 'is_controlled', 'expiry_date', 'synced'];

    protected function casts(): array
    {
        return ['is_controlled' => 'boolean', 'synced' => 'boolean', 'expiry_date' => 'date'];
    }

    public function isLowStock(): bool
    {
        return $this->quantity_on_hand <= $this->reorder_level;
    }
}
