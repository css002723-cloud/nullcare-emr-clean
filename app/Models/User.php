<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = [
        'full_name', 'username', 'email', 'password', 'role_id', 'department_id',
        'phone', 'status', 'is_active', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function registeredPatients()
    {
        return $this->hasMany(Patient::class, 'registered_by');
    }

    public function encounters()
    {
        return $this->hasMany(Encounter::class, 'clinician_id');
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }
}
