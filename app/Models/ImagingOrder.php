<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImagingOrder extends Model
{
    protected $fillable = [
        'client_uuid', 'order_id', 'encounter_id', 'patient_id', 'modality',
        'study_description', 'body_site', 'clinical_indication', 'accession_number',
        'study_instance_uid', 'is_pregnancy_checked', 'safety_checklist_notes',
        'status', 'ordered_by', 'priority', 'synced',
    ];

    protected function casts(): array
    {
        return ['is_pregnancy_checked' => 'boolean', 'synced' => 'boolean'];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function encounter()
    {
        return $this->belongsTo(Encounter::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function report()
    {
        return $this->hasOne(ImagingReport::class);
    }
}
