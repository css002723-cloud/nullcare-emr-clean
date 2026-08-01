<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipmentMaintenanceRecord extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'client_uuid', 'equipment_id', 'maintenance_type', 'performed_at',
        'performed_by_name', 'notes', 'cost', 'logged_by', 'synced',
    ];

    protected function casts(): array
    {
        return ['performed_at' => 'datetime', 'synced' => 'boolean'];
    }

    public function equipment()
    {
        return $this->belongsTo(Equipment::class);
    }

    public function loggedBy()
    {
        return $this->belongsTo(User::class, 'logged_by');
    }
}
