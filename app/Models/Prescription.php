<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Prescription extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'encounter_id', 'patient_id', 'prescribed_by', 'drug_name', 'formulation',
        'dose', 'route', 'frequency', 'duration', 'status',
        'is_pediatric_dose', 'cds_alerts',
    ];

    protected function casts(): array
    {
        return ['is_pediatric_dose' => 'boolean'];
    }

    // cds_alerts is intentionally NOT cast to array — the frontend stores/reads
    // it as a raw JSON string and does its own JSON.parse() on the list view,
    // while the create-response separately exposes a real array as
    // `cds_alerts_list` (built in the controller, not stored on the model).

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
