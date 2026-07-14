<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemAlert extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['type', 'message', 'severity', 'is_resolved'];

    protected function casts(): array
    {
        return ['is_resolved' => 'boolean'];
    }
}
