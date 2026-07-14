<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Prescription extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'encounter_id', 'patient_id', 'prescribed_by', 'drug_name', 'formulation',
        'dose', 'route', 'frequency', 'duration', 'status',
    ];

    public function encounter()
    {
        return $this->belongsTo(Encounter::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function prescribedBy()
    {
        return $this->belongsTo(User::class, 'prescribed_by');
    }

    public function dispensingRecords()
    {
        return $this->hasMany(DispensingRecord::class);
    }
}
