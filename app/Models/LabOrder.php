<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LabOrder extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'encounter_id', 'patient_id', 'ordered_by', 'test_name', 'loinc_code',
        'specimen_type', 'status', 'urgency', 'ordered_at',
    ];

    protected function casts(): array
    {
        return ['ordered_at' => 'datetime'];
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

    public function result()
    {
        return $this->hasOne(LabResult::class);
    }
}
