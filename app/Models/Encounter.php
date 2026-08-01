<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Encounter extends Model
{
    /**
     * All valid stage values, matching ENCOUNTER_STAGES in the Python
     * reference exactly — used for validation, not stored as an enum
     * constraint (stage is a plain string column).
     */
    const STAGES = [
        'registered', 'triage', 'waiting_consultation', 'in_consultation',
        'awaiting_orders', 'admitted', 'discharged', 'referred',
        'deceased', 'closed', 'cancelled',
    ];

    const CLOSED_STAGES = ['discharged', 'closed', 'deceased', 'cancelled'];

    protected $fillable = [
        'client_uuid', 'encounter_number', 'mrn', 'patient_id', 'visit_type', 'stage',
        'priority', 'is_emergency', 'chief_complaint', 'current_department',
        'assigned_clinician_id', 'ward', 'bed', 'admission_diagnosis', 'outcome',
        'disposition_notes', 'closed_at', 'registered_by', 'synced',
    ];

    protected function casts(): array
    {
        return [
            'is_emergency' => 'boolean',
            'synced' => 'boolean',
            'closed_at' => 'datetime',
        ];
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function assignedClinician()
    {
        return $this->belongsTo(User::class, 'assigned_clinician_id');
    }

    public function registeredBy()
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function vitals()
    {
        return $this->hasMany(Vital::class);
    }

    public function clinicalNotes()
    {
        return $this->hasMany(ClinicalNote::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function labOrders()
    {
        return $this->hasMany(LabOrder::class);
    }

    public function imagingOrders()
    {
        return $this->hasMany(ImagingOrder::class);
    }

    public function prescriptions()
    {
        return $this->hasMany(Prescription::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function referrals()
    {
        return $this->hasMany(Referral::class);
    }

    public function fluidBalanceEntries()
    {
        return $this->hasMany(FluidBalance::class);
    }

    public function icuNotes()
    {
        return $this->hasMany(ICUNote::class);
    }

    public function dialysisSessions()
    {
        return $this->hasMany(DialysisSession::class);
    }

    /**
     * The most recent non-declined referral for this encounter — mirrors
     * get_active_referral() in the Python reference. Used to show
     * "referred by Dr. X" wherever a patient currently sits.
     */
    public function activeReferral(): ?Referral
    {
        if (in_array($this->stage, self::CLOSED_STAGES, true)) {
            return null;
        }

        return $this->referrals()
            ->where('status', '!=', 'declined')
            ->latest()
            ->first();
    }

    /**
     * Composed summary dict matching get_active_referral()'s exact output
     * shape in the Python reference — resolves the sending clinician's
     * name/role rather than just returning raw referral columns.
     */
    public function activeReferralSummary(): ?array
    {
        $referral = $this->activeReferral();

        if (! $referral) {
            return null;
        }

        $sender = $referral->referredBy;

        return [
            'id' => $referral->id,
            'to_department' => $referral->to_department,
            'from_department' => $referral->from_department,
            'message' => $referral->reason,
            'status' => $referral->status,
            'referred_by_name' => $sender?->full_name,
            'referred_by_role' => $sender?->role,
            'created_at' => $referral->created_at?->toIso8601String(),
        ];
    }
}
