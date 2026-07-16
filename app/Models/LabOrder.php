<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LabOrder extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'encounter_id', 'patient_id', 'ordered_by', 'test_name', 'test_code', 'loinc_code',
        'specimen_type', 'barcode', 'status', 'urgency', 'ordered_at',
    ];

    protected function casts(): array
    {
        return ['ordered_at' => 'datetime'];
    }

    public function encounter()
    {
        return $this->belongsTo(Encounter::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function orderedBy()
    {
        return $this->belongsTo(User::class, 'ordered_by');
    }

    public function result()
    {
        return $this->hasOne(LabResult::class);
    }

    // test_code -> lab_test_catalog.test_code, gives us loinc_display without
    // duplicating catalog data onto every single lab order row.
    public function catalogEntry()
    {
        return $this->belongsTo(LabTestCatalog::class, 'test_code', 'test_code');
    }
}
