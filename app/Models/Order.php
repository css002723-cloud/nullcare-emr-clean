<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'client_uuid', 'encounter_id', 'patient_id', 'order_type', 'details', 'priority',
        'status', 'ordered_by', 'target_department', 'acknowledged_by', 'acknowledged_at', 'synced',
    ];

    protected function casts(): array
    {
        return ['acknowledged_at' => 'datetime', 'synced' => 'boolean'];
    }

    public function encounter()
    {
        return $this->belongsTo(Encounter::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function orderedBy()
    {
        return $this->belongsTo(User::class, 'ordered_by');
    }

    public function labOrder()
    {
        return $this->hasOne(LabOrder::class);
    }

    public function imagingOrder()
    {
        return $this->hasOne(ImagingOrder::class);
    }
}
