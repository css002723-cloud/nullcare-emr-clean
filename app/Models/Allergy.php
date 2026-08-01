<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Allergy extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['client_uuid', 'patient_id', 'substance', 'reaction', 'severity', 'recorded_by', 'synced'];

    protected function casts(): array
    {
        return ['synced' => 'boolean'];
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
