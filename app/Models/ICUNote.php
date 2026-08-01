<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ICUNote extends Model
{
    protected $table = 'icu_notes';

    protected $fillable = [
        'client_uuid', 'encounter_id', 'patient_id', 'note_type', 'ventilation_status',
        'oxygen_therapy', 'sedation_assessment', 'inotropes', 'fluid_balance_summary',
        'sepsis_alert', 'body', 'author_id', 'author_role', 'synced',
    ];

    protected function casts(): array
    {
        return ['sepsis_alert' => 'boolean', 'synced' => 'boolean'];
    }

    public function encounter()
    {
        return $this->belongsTo(Encounter::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
