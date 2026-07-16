<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VitalSign extends Model
{
    const UPDATED_AT = null;
    const CREATED_AT = null;

    protected $fillable = [
        'encounter_id', 'recorded_by', 'temperature', 'blood_pressure_systolic',
        'blood_pressure_diastolic', 'pulse_rate', 'respiratory_rate', 'oxygen_saturation',
        'weight_kg', 'height_cm', 'blood_glucose', 'pain_score', 'is_abnormal',
        'early_warning_score', 'recorded_at',
    ];

    protected function casts(): array
    {
        return ['is_abnormal' => 'boolean', 'recorded_at' => 'datetime'];
    }

    public function encounter()
    {
        return $this->belongsTo(Encounter::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
