<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicationAdministration extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['client_uuid', 'prescription_id', 'administered_by', 'administered_at', 'dose_given', 'notes', 'synced'];

    protected function casts(): array
    {
        return ['administered_at' => 'datetime', 'synced' => 'boolean'];
    }

    public function prescription()
    {
        return $this->belongsTo(Prescription::class);
    }
}
