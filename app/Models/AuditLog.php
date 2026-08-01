<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['timestamp', 'user_id', 'username', 'action', 'entity_type', 'entity_id', 'details', 'ip_address'];

    protected function casts(): array
    {
        return ['timestamp' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
