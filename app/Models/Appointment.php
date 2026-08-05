<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    public const STATUSES = ['scheduled', 'checked_in', 'completed', 'missed', 'cancelled'];

    protected $fillable = [
        'appointment_number', 'patient_id', 'doctor_id', 'department',
        'appointment_date', 'appointment_time', 'reason', 'priority', 'status',
        'contact_phone', 'encounter_id', 'booked_by', 'checked_in_at',
    ];

    protected function casts(): array
    {
        return [
            'appointment_date' => 'date',
            'checked_in_at' => 'datetime',
        ];
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function encounter()
    {
        return $this->belongsTo(Encounter::class);
    }

    public function bookedBy()
    {
        return $this->belongsTo(User::class, 'booked_by');
    }
}
