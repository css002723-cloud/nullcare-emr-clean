<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    protected $fillable = [
        'patient_id', 'department_id', 'provider_id',
        'appointment_date', 'status', 'visit_type',
    ];

    protected function casts(): array
    {
        return ['appointment_date' => 'datetime'];
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function provider()
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    public function encounter()
    {
        return $this->hasOne(Encounter::class);
    }
}
