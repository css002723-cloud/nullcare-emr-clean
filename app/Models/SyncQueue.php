<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncQueue extends Model
{
    public $timestamps = false;

    protected $table = 'sync_queue';

    protected $fillable = ['user_id', 'table_name', 'payload', 'status', 'created_offline_at', 'synced_at'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_offline_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
