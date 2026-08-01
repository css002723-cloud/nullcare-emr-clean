<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vital extends Model
{
    const UPDATED_AT = null;

    protected $table = 'vitals';

    protected $fillable = [
        'client_uuid', 'encounter_id', 'patient_id', 'temperature_c',
        'blood_pressure_systolic', 'blood_pressure_diastolic', 'pulse_rate',
        'respiratory_rate', 'spo2', 'weight_kg', 'height_cm', 'bmi', 'pain_score',
        'blood_glucose', 'gcs', 'early_warning_score', 'is_abnormal', 'abnormal_flags',
        'recorded_by', 'synced',
    ];

    protected function casts(): array
    {
        return ['is_abnormal' => 'boolean', 'synced' => 'boolean'];
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
