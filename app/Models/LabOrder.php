<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LabOrder extends Model
{
    /**
     * LOINC-ready common lab test catalog — a plain constant, not a DB
     * table, matching LOINC_TEST_CATALOG in the Python reference exactly.
     */
    const CATALOG = [
        'FBC' => ['loinc_code' => '58410-2', 'loinc_display' => 'CBC panel'],
        'MALARIA_RDT' => ['loinc_code' => '32700-7', 'loinc_display' => 'Malaria smear'],
        'HIV_TEST' => ['loinc_code' => '75622-1', 'loinc_display' => 'HIV rapid test'],
        'RENAL_PROFILE' => ['loinc_code' => '24362-6', 'loinc_display' => 'Kidney panel'],
        'LIVER_PROFILE' => ['loinc_code' => '24325-3', 'loinc_display' => 'Hepatic panel'],
        'BLOOD_GLUCOSE' => ['loinc_code' => '2345-7', 'loinc_display' => 'Glucose, serum/plasma'],
        'URINALYSIS' => ['loinc_code' => '24356-8', 'loinc_display' => 'Urinalysis panel'],
        'HB' => ['loinc_code' => '718-7', 'loinc_display' => 'Hemoglobin'],
        'CROSSMATCH' => ['loinc_code' => '44786-3', 'loinc_display' => 'Crossmatch compatibility'],
        'COVID_PCR' => ['loinc_code' => '94500-6', 'loinc_display' => 'SARS-CoV-2 RNA PCR'],
    ];

    protected $fillable = [
        'client_uuid', 'order_id', 'encounter_id', 'patient_id', 'test_code', 'loinc_code',
        'loinc_display', 'specimen_type', 'barcode', 'status', 'priority', 'ordered_by',
        'collected_by', 'collected_at', 'received_at', 'synced',
    ];

    protected function casts(): array
    {
        return ['collected_at' => 'datetime', 'received_at' => 'datetime', 'synced' => 'boolean'];
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

    public function result()
    {
        return $this->hasOne(LabResult::class);
    }
}
