<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipmentDowntimeReport extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'client_uuid', 'equipment_id', 'reported_by', 'started_at', 'resolved_at',
        'reason', 'impact_notes', 'status', 'synced',
    ];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'resolved_at' => 'datetime', 'synced' => 'boolean'];
    }

    public function equipment()
    {
        return $this->belongsTo(Equipment::class);
    }

    public function reportedBy()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
}
