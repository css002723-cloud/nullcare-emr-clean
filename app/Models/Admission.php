<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Admission extends Model
{
    const UPDATED_AT = null;
    const CREATED_AT = null;

    protected $fillable = [
        'patient_id', 'encounter_id', 'ward_name', 'bed_number',
        'admission_diagnosis', 'admitted_by', 'admitted_at',
        'discharged_at', 'discharge_summary', 'outcome',
    ];

    protected function casts(): array
    {
        return ['admitted_at' => 'datetime', 'discharged_at' => 'datetime'];
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function encounter()
    {
        return $this->belongsTo(Encounter::class);
    }

    public function admittedBy()
    {
        return $this->belongsTo(User::class, 'admitted_by');
    }
}
