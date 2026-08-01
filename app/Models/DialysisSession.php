<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DialysisSession extends Model
{
    protected $fillable = [
        'client_uuid', 'patient_id', 'encounter_id', 'ckd_stage', 'session_date',
        'pre_weight_kg', 'post_weight_kg', 'fluid_removal_target_l', 'vascular_access_type',
        'complications', 'status', 'performed_by', 'synced',
    ];

    protected function casts(): array
    {
        return ['session_date' => 'datetime', 'synced' => 'boolean'];
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function encounter()
    {
        return $this->belongsTo(Encounter::class);
    }

    public function performedBy()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
