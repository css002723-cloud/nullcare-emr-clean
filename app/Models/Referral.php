<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Referral extends Model
{
    protected $fillable = [
        'client_uuid', 'encounter_id', 'to_department', 'reason', 'created_by',
    ];

    public function encounter()
    {
        return $this->belongsTo(Encounter::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
