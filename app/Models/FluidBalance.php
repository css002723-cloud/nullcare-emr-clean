<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FluidBalance extends Model
{
    const UPDATED_AT = null;

    protected $table = 'fluid_balance';

    protected $fillable = [
        'client_uuid', 'encounter_id', 'patient_id', 'direction', 'category',
        'volume_ml', 'notes', 'recorded_by', 'recorded_at', 'synced',
    ];

    protected function casts(): array
    {
        return ['recorded_at' => 'datetime', 'synced' => 'boolean'];
    }

    public function encounter()
    {
        return $this->belongsTo(Encounter::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
