<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PharmacyStock extends Model
{
    const CREATED_AT = null;

    // Explicit table name required: the migration creates `pharmacy_stock`
    // (singular, matching the original schema doc), but Eloquent's default
    // naming convention would otherwise guess `pharmacy_stocks` (plural)
    // and silently fail with "table doesn't exist".
    protected $table = 'pharmacy_stock';

    protected $fillable = [
        'drug_name', 'batch_number', 'quantity_available',
        'reorder_threshold', 'expiry_date', 'is_controlled',
    ];

    protected function casts(): array
    {
        return ['expiry_date' => 'date', 'is_controlled' => 'boolean'];
    }

    public function isLowStock(): bool
    {
        return $this->quantity_available <= $this->reorder_threshold;
    }
}