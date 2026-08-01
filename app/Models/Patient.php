<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_uuid', 'patient_uid', 'national_id', 'given_name', 'family_name', 'sex',
        'date_of_birth', 'estimated_age', 'phone', 'village', 'traditional_authority',
        'district', 'region', 'occupation', 'guardian_name', 'guardian_relationship',
        'guardian_phone', 'patient_category', 'consent_care', 'consent_research',
        'consent_teaching', 'is_deceased', 'date_of_death', 'photo_url',
        'merged_into_patient_id', 'registered_by', 'synced',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'date_of_death' => 'datetime',
            'consent_care' => 'boolean',
            'consent_research' => 'boolean',
            'consent_teaching' => 'boolean',
            'is_deceased' => 'boolean',
            'synced' => 'boolean',
        ];
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->given_name} {$this->family_name}");
    }

    /**
     * Matches Patient.is_pediatric() in the Python reference exactly:
     * DOB-based age if known, else estimated_age, else assume not pediatric.
     */
    public function isPediatric(): bool
    {
        if ($this->date_of_birth) {
            return $this->date_of_birth->age < 18;
        }

        if ($this->estimated_age !== null) {
            return $this->estimated_age < 18;
        }

        return false;
    }

    public function registeredBy()
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function mergedInto()
    {
        return $this->belongsTo(Patient::class, 'merged_into_patient_id');
    }

    public function allergies()
    {
        return $this->hasMany(Allergy::class);
    }

    public function encounters()
    {
        return $this->hasMany(Encounter::class);
    }
}
