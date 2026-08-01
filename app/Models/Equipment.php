<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Equipment extends Model
{
    /** Matches EQUIPMENT_STATUSES in the Python reference exactly. */
    const STATUSES = ['operational', 'under_maintenance', 'down'];

    protected $fillable = [
        'client_uuid', 'name', 'equipment_type', 'department', 'serial_number', 'status',
        'install_date', 'last_maintenance_at', 'next_maintenance_due', 'notes', 'synced',
    ];

    protected function casts(): array
    {
        return [
            'install_date' => 'date',
            'last_maintenance_at' => 'datetime',
            'next_maintenance_due' => 'date',
            'synced' => 'boolean',
        ];
    }

    public function maintenanceRecords()
    {
        return $this->hasMany(EquipmentMaintenanceRecord::class);
    }

    public function downtimeReports()
    {
        return $this->hasMany(EquipmentDowntimeReport::class);
    }

    public function isMaintenanceOverdue(): bool
    {
        return $this->next_maintenance_due && $this->next_maintenance_due->isPast();
    }
}
