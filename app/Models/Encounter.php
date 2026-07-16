<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Encounter extends Model
{
    protected $fillable = [
        'encounter_number', 'client_uuid', 'patient_id', 'appointment_id', 'clinician_id', 'department_id',
        'encounter_type', 'triage_category', 'presenting_complaint', 'history',
        'examination_findings', 'diagnosis', 'diagnosis_code', 'clinical_plan', 'disposition_notes',
        'status', 'stage', 'current_department',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function clinician()
    {
        return $this->belongsTo(User::class, 'clinician_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function vitalSigns()
    {
        return $this->hasMany(VitalSign::class);
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

    public function admission()
    {
        return $this->hasOne(Admission::class);
    }

    public function clinicalNotes()
    {
        return $this->hasMany(ClinicalNote::class);
    }

    public function clinicalOrders()
    {
        return $this->hasMany(ClinicalOrder::class);
    }

    public function referrals()
    {
        return $this->hasMany(Referral::class);
    }
}
