<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable, HasFactory;

    const ROLES = [
        'admin', 'reception', 'nurse', 'doctor', 'lab_tech',
        'radiologist', 'pharmacist', 'billing', 'dialysis_tech', 'records_officer',
    ];

    /** Matches PASSWORD_MAX_AGE_DAYS in the Python reference exactly (180 days / ~6 months). */
    const PASSWORD_MAX_AGE_DAYS = 180;

    protected $fillable = [
        'first_name', 'last_name', 'username', 'email', 'password', 'role', 'department',
        'phone', 'is_active', 'must_reset_password', 'password_changed_at',
        'last_login_at', 'client_uuid', 'synced',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'must_reset_password' => 'boolean',
            'synced' => 'boolean',
            'password_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** full_name is computed, not stored — matches the reference's @property exactly. */
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function isPasswordExpired(): bool
    {
        if (! $this->password_changed_at) {
            return true;
        }

        return $this->password_changed_at->diffInDays(now()) >= self::PASSWORD_MAX_AGE_DAYS;
    }

    public function registeredPatients()
    {
        return $this->hasMany(Patient::class, 'registered_by');
    }

    public function encounters()
    {
        return $this->hasMany(Encounter::class, 'assigned_clinician_id');
    }
}
