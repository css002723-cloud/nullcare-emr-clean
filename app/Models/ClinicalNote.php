<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicalNote extends Model
{
    protected $fillable = [
        'client_uuid', 'encounter_id', 'note_type', 'diagnosis', 'plan', 'body', 'recorded_by',
    ];

    public function encounter()
    {
        return $this->belongsTo(Encounter::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
