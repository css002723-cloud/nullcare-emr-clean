<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Prescription extends Model
{
    protected $fillable = [
        'client_uuid', 'encounter_id', 'patient_id', 'drug_name', 'formulation', 'dose',
        'route', 'frequency', 'duration', 'is_pediatric_dose', 'cds_alerts', 'status',
        'prescribed_by', 'dispensed_by', 'dispensed_at', 'allergy_override_by', 'allergy_override_at', 'synced',
    ];

    protected function casts(): array
    {
        return [
            'is_pediatric_dose' => 'boolean',
            'synced' => 'boolean',
            'dispensed_at' => 'datetime',
            'allergy_override_at' => 'datetime',
        ];
    }

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

    public function administrations()
    {
        return $this->hasMany(MedicationAdministration::class);
    }
}
