<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginAttempt extends Model
{
    public $timestamps = false;

    protected $fillable = ['username', 'ip_address', 'success', 'timestamp'];

    protected function casts(): array
    {
        return ['success' => 'boolean', 'timestamp' => 'datetime'];
    }
}
