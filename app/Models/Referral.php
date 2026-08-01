<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Referral extends Model
{
    protected $fillable = [
        'client_uuid', 'encounter_id', 'patient_id', 'from_department', 'to_department',
        'reason', 'priority', 'status', 'referred_by', 'accepted_by', 'is_read', 'synced',
    ];

    protected function casts(): array
    {
        return ['is_read' => 'boolean', 'synced' => 'boolean'];
    }

    public function encounter()
    {
        return $this->belongsTo(Encounter::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function referredBy()
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    public function acceptedBy()
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }
}
