<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    use HasFactory;
    protected $fillable = [
        'client_uuid', 'patient_number', 'national_id', 'first_name', 'last_name', 'gender',
        'date_of_birth', 'age_estimate', 'phone', 'email', 'address', 'village',
        'traditional_authority', 'district', 'region', 'occupation', 'patient_category',
        'guardian_name', 'guardian_phone', 'guardian_relationship',
        'consent_care', 'consent_teaching', 'consent_research',
        'is_deceased', 'referred_doctor', 'referred_doctor_department', 'completion_status',
        'is_duplicate_of', 'registered_by',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'consent_care' => 'boolean',
            'consent_teaching' => 'boolean',
            'consent_research' => 'boolean',
            'is_deceased' => 'boolean',
        ];
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function registeredBy()
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function duplicateOf()
    {
        return $this->belongsTo(Patient::class, 'is_duplicate_of');
    }

    public function allergies()
    {
        return $this->hasMany(PatientAllergy::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function encounters()
    {
        return $this->hasMany(Encounter::class);
    }

    public function labOrders()
    {
        return $this->hasMany(LabOrder::class);
    }

    public function prescriptions()
    {
        return $this->hasMany(Prescription::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function admissions()
    {
        return $this->hasMany(Admission::class);
    }
}
