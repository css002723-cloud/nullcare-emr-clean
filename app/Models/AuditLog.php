<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'action', 'table_name', 'record_id',
        'old_value', 'new_value', 'ip_address',
    ];

    protected function casts(): array
    {
        return ['old_value' => 'array', 'new_value' => 'array'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
